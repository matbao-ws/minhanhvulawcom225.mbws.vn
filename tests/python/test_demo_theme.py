"""The core checkout's local demo theme never leaves it: python -m unittest discover -s tests/python"""
import os
import subprocess
import sys

sys.dont_write_bytecode = True  # no __pycache__ next to the scripts
import unittest

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
sys.path.insert(0, ROOT)
import deploy  # noqa: E402


class DemoThemeTest(unittest.TestCase):

    def test_deploy_never_uploads_it(self):
        for path in ('demo-theme/routes.php', 'demo-theme/views/client/pages/home.blade.php',
                     'public/demo-theme-assets/css/storefront.css', 'public\demo-theme-assets\images\a.jpg'):
            self.assertEqual('entirely', deploy.should_exclude(path), path)

    def test_the_website_theme_is_still_uploaded(self):
        self.assertEqual('none', deploy.should_exclude('resources/views/client/layouts/app.blade.php'))
        self.assertEqual('none', deploy.should_exclude('public/client-assets/css/storefront.css'))

    def test_git_ignores_it_so_new_projects_never_get_it(self):
        # `team.py new` clones the core repository: what git ignores is never in a new project.
        for path in ('demo-theme/routes.php', 'public/demo-theme-assets/css/storefront.css'):
            result = subprocess.run(['git', 'check-ignore', '-q', path], cwd=ROOT)
            self.assertEqual(0, result.returncode, path)


if __name__ == '__main__':
    unittest.main()
