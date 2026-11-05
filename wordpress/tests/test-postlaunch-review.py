import importlib.util
import pathlib
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('review', pathlib.Path(__file__).parents[1] / 'scripts/postlaunch-review.py')
review = importlib.util.module_from_spec(spec)
spec.loader.exec_module(review)

class Deadlines(unittest.TestCase):
    release = {'cutoverAt': '2026-11-04T07:20:33.125473Z'}

    def test_boundaries_and_local_dates(self):
        before = review.plan(self.release, review.instant('2026-11-07T07:20:33Z'))
        self.assertEqual(before[0]['status'], 'not_due')
        at = review.plan(self.release, review.instant('2026-11-07T07:20:33.125473Z'))
        self.assertEqual(at[0]['status'], 'due')
        self.assertEqual(at[1]['status'], 'not_due')
        self.assertTrue(at[1]['dueLocal'].startswith('2026-11-11T15:20:33'))

    def test_cannot_record_early_or_without_all_evidence(self):
        with tempfile.TemporaryDirectory() as root:
            with self.assertRaises(ValueError):
                review.record(self.release, '72h', {}, review.instant('2026-11-05T00:00:00Z'), root)
            with self.assertRaises(ValueError):
                review.record(self.release, '72h', {}, review.instant('2026-11-08T00:00:00Z'), root)
            self.assertEqual(list(pathlib.Path(root).iterdir()), [])

    def test_late_execution_does_not_claim_past_or_acceptance(self):
        with tempfile.TemporaryDirectory() as root:
            evidence = pathlib.Path(root) / 'evidence.json'
            evidence.write_text('{"status":"pending_indexing"}')
            now = review.instant('2026-11-08T00:00:00Z')
            result = review.record(self.release, '72h', {x:str(evidence) for x in review.REQUIRED}, now, pathlib.Path(root)/'journal')
            self.assertEqual(result['executedAt'], now.isoformat())
            self.assertGreater(result['secondsAfterDue'], 0)
            self.assertEqual(result['status'], 'executed_pending_review')
            self.assertEqual(len(result['evidence']['google']['sha256']), 64)

if __name__ == '__main__':
    unittest.main()
