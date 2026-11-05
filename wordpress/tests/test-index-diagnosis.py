import importlib.util
import pathlib
import unittest

spec=importlib.util.spec_from_file_location('diagnosis',pathlib.Path(__file__).parents[1]/'scripts/build-index-diagnosis.py')
d=importlib.util.module_from_spec(spec);spec.loader.exec_module(d)

class Classification(unittest.TestCase):
    url='https://www.chinamudlogging.com/product/test/'
    def analyse(self,google=None,http=None,cms=None):
        good={'status':200,'finalUrl':self.url,'canonical':[self.url],'h1':['Test'],'lang':'en-US','robots':['index, follow'],'articleText':'Technical source.'}
        if http:good.update(http)
        return d.analyse(self.url,{'language':'en','coverage':'URL is unknown to Google'},google or {'result':{'indexStatusResult':{'coverageState':'URL is unknown to Google'}}},good,cms or {'type':'cml_product','publishedAt':'2026-11-05 01:00:00'},'2026-11-05T01:00:00Z',True,{'https://www.chinamudlogging.com/product/'},set())
    def test_unknown_recent_page_is_not_a_technical_failure(self):
        row=self.analyse();self.assertEqual(row['category'],'new_publication_wait');self.assertEqual(row['technicalIssues'],[])
    def test_http_200_does_not_override_noindex_or_wrong_canonical(self):
        row=self.analyse(http={'robots':['noindex, follow'],'canonical':['https://example.invalid/']})
        self.assertEqual(row['category'],'technical_issue');self.assertIn('live_noindex',row['technicalIssues']);self.assertIn('live_canonical',row['technicalIssues'])
    def test_old_google_snapshot_requires_new_crawl(self):
        row=self.analyse(google={'result':{'indexStatusResult':{'coverageState':'Crawled - currently not indexed','lastCrawlTime':'2026-08-01T00:00:00Z'}}})
        self.assertEqual(row['category'],'await_updated_content_crawl');self.assertTrue(row['crawlPredatesCurrentContent'])
    def test_short_text_is_review_flag_not_google_failure_reason(self):
        row=self.analyse(cms={'type':'post','publishedAt':'2022-01-01 00:00:00'})
        self.assertEqual(row['category'],'legacy_content_review');self.assertEqual(row['technicalIssues'],[])
        row=self.analyse(google={'result':{'indexStatusResult':{'verdict':'PASS','coverageState':'Submitted and indexed'}}},cms={'type':'post'})
        self.assertEqual(row['category'],'indexed')
    def test_api_error_cannot_become_normal_wait(self):
        row=self.analyse(google={'error':'permission unavailable'})
        self.assertEqual(row['category'],'google_status_unavailable')

if __name__=='__main__':unittest.main()
