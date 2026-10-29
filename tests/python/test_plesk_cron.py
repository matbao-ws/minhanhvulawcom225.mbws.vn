"""Which websites infra/plesk-cron.sh finds: python -m unittest discover -s tests/python

The shared cron runs `schedule:run` (and so the core updates websites request) only in the sites
its scan finds. A site it misses looks healthy and never updates: requested.json just waits.
"""
import os
import shutil
import subprocess
import sys
import tempfile
import unittest

sys.dont_write_bytecode = True

SCRIPT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..', 'infra', 'plesk-cron.sh'))


def make_site(vhosts, *parts):
    root = os.path.join(vhosts, *parts)
    os.makedirs(os.path.join(root, 'app', 'Support'))
    open(os.path.join(root, 'artisan'), 'w').close()
    open(os.path.join(root, 'app', 'Support', 'CoreManifest.php'), 'w').close()
    return root


@unittest.skipUnless(os.name == 'posix' and shutil.which('bash') and shutil.which('find'), 'needs bash and GNU find')
class FindCoreRootsTest(unittest.TestCase):

    def setUp(self):
        self.vhosts = tempfile.mkdtemp()
        self.addCleanup(shutil.rmtree, self.vhosts)

    def roots(self):
        env = dict(os.environ, VHOSTS=self.vhosts)
        result = subprocess.run(['bash', '-c', 'source "$1"; find_core_roots', '_', SCRIPT],
                                env=env, capture_output=True, text=True, check=True)
        return sorted(os.path.relpath(line, self.vhosts).replace(os.sep, '/') for line in result.stdout.split())

    def test_sites_at_every_layout_we_deploy_are_found(self):
        make_site(self.vhosts, 'shop.vn', 'httpdocs')
        make_site(self.vhosts, 'shop.vn', 'sub.shop.vn')
        # caoxoatramhuong.vn, GreenX, Mây Pet: artisan at depth 5 was missed with -maxdepth 4.
        make_site(self.vhosts, 'caoxoatramhuong.vn', 'httpdocs', 'backend', 'public')

        self.assertEqual(['caoxoatramhuong.vn/httpdocs/backend/public', 'shop.vn/httpdocs', 'shop.vn/sub.shop.vn'], self.roots())

    def test_vendor_storage_system_and_other_laravel_apps_are_not_sites(self):
        make_site(self.vhosts, 'shop.vn', 'httpdocs')
        # A package or a backup copy inside the site, Plesk's own trees, and a Laravel app that is not this core.
        make_site(self.vhosts, 'shop.vn', 'httpdocs', 'vendor', 'some', 'package')
        make_site(self.vhosts, 'shop.vn', 'httpdocs', 'storage', 'app', 'backup')
        make_site(self.vhosts, 'system', 'shop.vn', 'conf')
        make_site(self.vhosts, 'chroot', 'x')
        other = os.path.join(self.vhosts, 'other.vn', 'httpdocs')
        os.makedirs(other)
        open(os.path.join(other, 'artisan'), 'w').close()

        self.assertEqual(['shop.vn/httpdocs'], self.roots())

    def test_sourcing_the_script_runs_nothing(self):
        env = dict(os.environ, VHOSTS=self.vhosts, LOG=os.path.join(self.vhosts, 'log'), LOCK_DIR=os.path.join(self.vhosts, 'lock'))
        result = subprocess.run(['bash', '-c', 'source "$1"; echo sourced', '_', SCRIPT], env=env, capture_output=True, text=True)

        self.assertEqual('sourced', result.stdout.strip())
        self.assertFalse(os.path.exists(os.path.join(self.vhosts, 'lock')))

    def test_only_an_existing_non_root_account_runs_a_website(self):
        # stat prints UNKNOWN for a uid with no account; runuser -u UNKNOWN failed every minute on s252.
        def runnable(owner):
            return subprocess.run(['bash', '-c', 'source "$1"; runnable_owner "$2"', '_', SCRIPT, owner]).returncode == 0

        import getpass
        self.assertTrue(runnable(getpass.getuser()) or getpass.getuser() == 'root')
        for owner in ['', 'root', 'UNKNOWN', 'no-such-user-matbaows']:
            self.assertFalse(runnable(owner), owner)


if __name__ == '__main__':
    unittest.main()
