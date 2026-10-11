"""Tests of team.py's `ci-audit` text edit: python -m unittest discover -s tests/python"""
import difflib
import os
import sys

sys.dont_write_bytecode = True  # no __pycache__ next to team.py
import unittest

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..')))
import team  # noqa: E402

# The CI that `team.py new` wrote before the audit step existed.
OLD_PROJECT_CI = team.PROJECT_CI.replace(
    f'      - name: {team.AUDIT_STEP_NAME}\n        run: {team.AUDIT_COMMAND}\n\n', '')


class AddAuditStepTest(unittest.TestCase):

    def test_new_projects_already_run_the_audit_before_the_tests(self):
        self.assertIn(team.AUDIT_COMMAND, team.PROJECT_CI)
        self.assertLess(team.PROJECT_CI.index(team.AUDIT_COMMAND), team.PROJECT_CI.index('php artisan test --compact'))
        self.assertEqual(('present', team.PROJECT_CI), team.add_audit_step(team.PROJECT_CI))

    def test_an_older_project_ci_gains_only_the_audit_step(self):
        self.assertNotIn(team.AUDIT_COMMAND, OLD_PROJECT_CI)

        status, text = team.add_audit_step(OLD_PROJECT_CI)

        self.assertEqual('added', status)
        self.assertEqual(team.PROJECT_CI, text, 'the result is exactly what new projects get')
        added = [l[2:] for l in difflib.ndiff(OLD_PROJECT_CI.split('\n'), text.split('\n')) if l.startswith('+ ')]
        removed = [l for l in difflib.ndiff(OLD_PROJECT_CI.split('\n'), text.split('\n')) if l.startswith('- ')]
        self.assertEqual([f'      - name: {team.AUDIT_STEP_NAME}', f'        run: {team.AUDIT_COMMAND}', ''], added)
        self.assertEqual([], removed, 'no existing line changes')

    def test_running_it_twice_adds_nothing_more(self):
        _, once = team.add_audit_step(OLD_PROJECT_CI)
        self.assertEqual(('present', once), team.add_audit_step(once))

    def test_a_customised_test_step_keeps_its_own_indentation_and_neighbours(self):
        ci = ('jobs:\n  build:\n    steps:\n'
              '    - uses: actions/checkout@v4\n'
              '    - run: php artisan test --parallel\n'
              '    - name: Deploy preview\n      run: ./preview.sh\n')

        status, text = team.add_audit_step(ci)

        self.assertEqual('added', status)
        self.assertIn(f'    - name: {team.AUDIT_STEP_NAME}\n      run: {team.AUDIT_COMMAND}\n\n    - run: php artisan test --parallel', text)
        self.assertTrue(text.endswith('    - name: Deploy preview\n      run: ./preview.sh\n'))

    def test_a_workflow_it_cannot_read_is_left_alone(self):
        ci = 'jobs:\n  test:\n    steps:\n      - run: vendor/bin/phpunit\n'

        self.assertEqual(('unknown', ci), team.add_audit_step(ci))


if __name__ == '__main__':
    unittest.main()
