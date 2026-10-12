"""Console input and colour of deploy.py and team.py: python -m unittest discover -s tests/python"""
import builtins
import io
import os
import sys

sys.dont_write_bytecode = True  # no __pycache__ next to the scripts
import unittest
from unittest import mock

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..')))
import deploy  # noqa: E402
import team  # noqa: E402

TOOLS = (deploy, team)


def colours_off(tool):
    for name in ('RESET', 'BOLD', 'DIM', 'RED', 'GREEN', 'YELLOW', 'BLUE', 'MAGENTA', 'CYAN', 'WHITE'):
        setattr(tool.C, name, '')


class CleanTest(unittest.TestCase):

    def test_spaces_and_invisible_characters_around_a_pasted_value_are_dropped(self):
        for tool in TOOLS:
            self.assertEqual('p@ss#word', tool.clean('  p@ss#word \t'))
            self.assertEqual('p@ss#word', tool.clean(' p@ss#word '))
            self.assertEqual('p@ss#word', tool.clean('﻿p@ss​#word‍'))
            self.assertEqual('', tool.clean(None))

    def test_spaces_inside_a_value_are_kept(self):
        for tool in TOOLS:
            self.assertEqual('Shop Túi Vải', tool.clean('  Shop Túi Vải  '))

    def test_the_two_copies_stay_identical(self):
        for name in ('clean', 'paint_line', 'prompt_line', 'read_answer', 'enable_console'):
            self.assertEqual(getattr(deploy, name).__code__.co_code, getattr(team, name).__code__.co_code, name)


class AskTest(unittest.TestCase):

    def setUp(self):
        colours_off(deploy)
        colours_off(team)

    def test_deploy_trims_and_shows_a_password(self):
        with mock.patch.object(builtins, 'input', return_value='  s3cret#Pass  ') as typed:
            self.assertEqual('s3cret#Pass', deploy.ask('FTP password', secret=True))
        # Shown, not hidden: the prompt goes through input(), not getpass.
        typed.assert_called_once()
        self.assertNotIn('getpass', sys.modules.get('deploy').__dict__)

    def test_deploy_shows_the_saved_password_as_the_default(self):
        with mock.patch.object(builtins, 'input', return_value='') as typed:
            self.assertEqual('old#pass', deploy.ask('FTP password', 'old#pass', secret=True))
        self.assertIn('[old#pass]', typed.call_args[0][0])

    def test_team_trims_answers(self):
        with mock.patch.object(builtins, 'input', return_value='  tuivai-phuongnam ​'):
            self.assertEqual('tuivai-phuongnam', team.ask('Tên dự án'))

    def test_yes_no_accepts_padded_answers(self):
        with mock.patch.object(builtins, 'input', return_value='  k '):
            self.assertFalse(team.yes('Tiếp tục?'))
        with mock.patch.object(builtins, 'input', return_value=''):
            self.assertTrue(team.yes('Tiếp tục?'))
        with mock.patch.object(builtins, 'input', return_value=' C '):
            self.assertTrue(deploy.prompt_yes_no('Tiến hành?', False, mock.Mock(yes=False)))

    def test_a_pasted_panel_block_is_trimmed(self):
        lines = iter(['  DB_HOST = localhost:3306  ', '﻿DB_PASS=  "x#y z" ', '   '])
        with mock.patch.object(builtins, 'input', lambda *_: next(lines)):
            values = deploy.read_pasted_block()
        self.assertEqual('localhost:3306', values['DB_HOST'])
        self.assertEqual('x#y z', values['DB_PASSWORD'])


class PaintTest(unittest.TestCase):

    def tearDown(self):
        colours_off(deploy)

    def test_without_a_terminal_nothing_is_coloured(self):
        colours_off(deploy)
        self.assertEqual('[+] Đã ghi .env lên server.', deploy.paint_line('[+] Đã ghi .env lên server.'))

    def test_each_tag_gets_its_colour_and_the_text_is_unchanged(self):
        deploy.C.enable()
        painted = deploy.paint_line('    [-] Gateway trả lỗi 500')
        self.assertTrue(painted.startswith('    \033['))
        self.assertIn('\033[91m', painted)  # red
        self.assertEqual('    [-] Gateway trả lỗi 500', strip_ansi(painted))
        self.assertIn('\033[92m', deploy.paint_line('[+] ok'))  # green
        self.assertIn('\033[93m', deploy.paint_line('[!] chú ý'))  # yellow
        self.assertEqual('plain text', deploy.paint_line('plain text'))

    def test_print_is_painted_line_by_line_through_the_wrapper(self):
        deploy.C.enable()
        buffer = io.StringIO()
        stream = deploy._PaintedStream(buffer)
        print('[+] một', file=stream)
        print('giữa [-] không phải tag', file=stream)
        stream.write('[-] hai\n[!] ba\n')
        lines = buffer.getvalue().splitlines()
        self.assertIn('\033[92m', lines[0])
        self.assertEqual('giữa [-] không phải tag', lines[1])
        self.assertIn('\033[91m', lines[2])
        self.assertIn('\033[93m', lines[3])
        self.assertEqual(['[+] một', 'giữa [-] không phải tag', '[-] hai', '[!] ba'], [strip_ansi(x) for x in lines])


class InterruptTest(unittest.TestCase):

    def run_deploy(self, stdin=''):
        import subprocess
        root = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
        return subprocess.run([sys.executable, '-B', os.path.join(root, 'deploy.py')], input=stdin, capture_output=True,
                              text=True, encoding='utf-8', cwd=root, timeout=60,
                              env={**os.environ, 'NO_COLOR': '1', 'PYTHONIOENCODING': 'utf-8'})

    def test_leaving_deploy_at_a_question_is_a_clean_exit_not_a_traceback(self):
        # stdin closed at the first question: the same path as Ctrl+Z/Ctrl+D, and Ctrl+C.
        result = self.run_deploy()
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn('[+] Đã thoát', result.stdout)
        self.assertNotIn('Traceback', result.stderr)

    def test_goodbye_warns_instead_when_the_deploy_was_writing(self):
        colours_off(deploy)
        buffer = io.StringIO()
        with mock.patch.object(sys, 'stdout', buffer):
            deploy.goodbye('Đã dừng giữa chừng')
        self.assertIn('[!] Đã dừng giữa chừng', buffer.getvalue())
        self.assertNotIn('Đã thoát', buffer.getvalue())

    def test_both_tools_say_goodbye_the_same_way(self):
        self.assertEqual(deploy.goodbye.__code__.co_code, team.goodbye.__code__.co_code)


def strip_ansi(text):
    import re
    return re.sub(r'\033\[[0-9;]*m', '', text)


if __name__ == '__main__':
    unittest.main()
