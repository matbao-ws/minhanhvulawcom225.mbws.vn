"""deploy.py asks for the package domain and checks it on the internal tool: python -m unittest discover -s tests/python"""
import argparse
import builtins
import io
import json
import os
import sys

sys.dont_write_bytecode = True  # no __pycache__ next to the scripts
import unittest
from unittest import mock

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..')))
import deploy  # noqa: E402

INFO_URL = 'https://tool.test/info.php'


def tool_answers(*bodies):
    """urlopen stand-in returning each body in turn (an exception instance is raised)."""
    replies = list(bodies)

    def urlopen(request, timeout=None):
        reply = replies.pop(0)
        if isinstance(reply, Exception):
            raise reply
        response = mock.MagicMock()
        response.__enter__.return_value.read.return_value = json.dumps(reply).encode()
        return response

    return mock.patch.object(deploy.urllib.request, 'urlopen', side_effect=urlopen)


def args(**overrides):
    values = {'yes': False, 'package_domain': None}
    values.update(overrides)
    return argparse.Namespace(**values)


FOUND = {'retCode': 0, 'data': {'code': 'hero1', 'packageName': 'Hero 1', 'dataUnit': 300, 'numberEmail': 0}}
NOT_FOUND = {'retCode': 1, 'message': 'not found'}


class CheckPackageTest(unittest.TestCase):

    def test_a_known_domain_returns_its_package(self):
        with tool_answers(FOUND) as urlopen:
            status, data = deploy.check_package('shop.mbws.vn', INFO_URL)
        self.assertEqual('ok', status)
        self.assertEqual('hero1', data['code'])
        self.assertEqual(INFO_URL + '/shop.mbws.vn', urlopen.call_args[0][0].full_url)

    def test_an_unknown_domain_is_not_found(self):
        with tool_answers(NOT_FOUND):
            self.assertEqual(('not_found', None), deploy.check_package('nope.mbws.vn', INFO_URL))

    def test_a_network_error_is_unreachable(self):
        with tool_answers(OSError('down')):
            self.assertEqual(('unreachable', None), deploy.check_package('shop.mbws.vn', INFO_URL))


class ResolvePackageDomainTest(unittest.TestCase):

    def setUp(self):
        self.output = mock.patch('sys.stdout', new_callable=io.StringIO).start()
        self.addCleanup(mock.patch.stopall)

    def test_the_deploy_domain_is_offered_and_kept_when_the_tool_knows_it(self):
        with tool_answers(FOUND), mock.patch.object(builtins, 'input', return_value='') as typed:
            self.assertEqual('shop.mbws.vn', deploy.resolve_package_domain('shop.mbws.vn', INFO_URL, args()))
        self.assertIn('[shop.mbws.vn]', typed.call_args[0][0])
        self.assertIn('HERO1', self.output.getvalue())
        self.assertIn('300 sản phẩm + bài viết', self.output.getvalue())

    def test_an_unknown_domain_is_asked_again(self):
        with tool_answers(NOT_FOUND, FOUND), mock.patch.object(builtins, 'input', side_effect=['wrong.vn', 'https://shop.mbws.vn/']):
            self.assertEqual('shop.mbws.vn', deploy.resolve_package_domain('shop.vn', INFO_URL, args()))
        self.assertIn('không có dự án wrong.vn', self.output.getvalue())

    def test_a_scripted_run_stops_on_an_unknown_domain(self):
        with tool_answers(NOT_FOUND), self.assertRaises(SystemExit):
            deploy.resolve_package_domain('shop.vn', INFO_URL, args(yes=True))

    def test_a_scripted_run_stops_when_the_tool_is_down(self):
        with tool_answers(OSError('down')), self.assertRaises(SystemExit):
            deploy.resolve_package_domain('shop.mbws.vn', INFO_URL, args(yes=True))

    def test_the_domain_can_be_kept_while_the_tool_is_down_when_confirmed(self):
        with tool_answers(OSError('down')), mock.patch.object(builtins, 'input', side_effect=['', 'c']):
            self.assertEqual('shop.mbws.vn', deploy.resolve_package_domain('shop.mbws.vn', INFO_URL, args()))

    def test_the_flag_is_used_without_asking(self):
        with tool_answers(FOUND), mock.patch.object(builtins, 'input') as typed:
            self.assertEqual('shop.mbws.vn', deploy.resolve_package_domain(
                'other.vn', INFO_URL, args(yes=True, package_domain='shop.mbws.vn')))
        typed.assert_not_called()


if __name__ == '__main__':
    unittest.main()
