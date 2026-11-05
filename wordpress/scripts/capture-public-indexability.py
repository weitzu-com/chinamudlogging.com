#!/usr/bin/env python3
"""Paced, resumable public HTTP capture. Does not execute tracking or submit forms."""
import argparse
import datetime as dt
import hashlib
from html.parser import HTMLParser
import json
import pathlib
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

BASE = 'https://www.chinamudlogging.com'
VOID = {'area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'}

def now():
    return dt.datetime.now(dt.timezone.utc).isoformat()

class Page(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.stack=[]
        self.data={'title':[], 'h1':[], 'canonical':[], 'robots':[], 'description':[], 'hreflang':[], 'links':[], 'images':[], 'mainText':[], 'articleText':[], 'tableRows':[], 'lang':'', 'dir':''}

    def handle_starttag(self, tag, attributes):
        a=dict(attributes)
        if tag=='html':
            self.data['lang']=a.get('lang','');self.data['dir']=a.get('dir','')
        if tag=='link':
            if 'canonical' in a.get('rel','').split():self.data['canonical'].append(a.get('href',''))
            if a.get('hreflang'):self.data['hreflang'].append({'language':a['hreflang'],'url':a.get('href','')})
        if tag=='meta':
            name=a.get('name','').lower()
            if name in ['robots','googlebot','description']:self.data['description' if name=='description' else 'robots'].append(a.get('content',''))
        inmain=any(x[0]=='main' for x in self.stack)
        if tag=='a' and a.get('href'):
            self.data['links'].append({'href':a['href'],'rel':a.get('rel',''),'main':inmain})
        if tag=='img' and inmain:self.data['images'].append({'src':a.get('src',''),'alt':a.get('alt')})
        if tag=='h1':self.data['h1'].append([])
        if tag=='tr' and inmain:self.data['tableRows'].append([])
        if tag not in VOID:self.stack.append((tag,a.get('class','').split()))

    def handle_endtag(self, tag):
        for i in range(len(self.stack)-1,-1,-1):
            if self.stack[i][0]==tag:
                self.stack=self.stack[:i];break

    def handle_data(self, text):
        if any(x[0] in ['script','style','noscript','svg'] for x in self.stack):return
        tags=[x[0] for x in self.stack]
        if 'title' in tags:self.data['title'].append(text)
        if 'h1' in tags and self.data['h1']:self.data['h1'][-1].append(text)
        if 'main' in tags:
            self.data['mainText'].append(text)
            if 'tr' in tags and self.data['tableRows']:self.data['tableRows'][-1].append(text)
        if any('entry-content' in x[1] for x in self.stack):self.data['articleText'].append(text)

    def result(self):
        normalize=lambda value:' '.join(' '.join(value).split())
        for key in ['title','mainText','articleText']:self.data[key]=normalize(self.data[key])
        for key in ['h1','tableRows']:self.data[key]=[normalize(x) for x in self.data[key]]
        return self.data

class Redirects(urllib.request.HTTPRedirectHandler):
    def __init__(self):self.chain=[]
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        self.chain.append({'from':req.full_url,'status':code,'to':newurl})
        if urllib.parse.urlsplit(newurl).netloc!='www.chinamudlogging.com':
            raise ValueError('External redirect; recorded but not followed')
        return super().redirect_request(req,fp,code,msg,headers,newurl)

def fetch(url):
    handler=Redirects()
    opener=urllib.request.build_opener(handler)
    request=urllib.request.Request(url,headers={'User-Agent':'ChinaMudLogging-Acceptance/1.0','Cache-Control':'no-cache'})
    try:response=opener.open(request,timeout=20)
    except urllib.error.HTTPError as error:response=error
    with response:
        raw=response.read(4_000_001)
        if len(raw)>4_000_000:raise ValueError('Response size limit')
        return response.status,response.geturl(),dict(response.headers),raw,handler.chain

def save(path, data):
    temporary=path.with_suffix(path.suffix+'.tmp')
    temporary.write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n');temporary.chmod(0o600)
    temporary.replace(path)

def capture(directory, targets):
    directory.mkdir(parents=True,mode=0o700,exist_ok=True)
    html=directory/'html';html.mkdir(mode=0o700,exist_ok=True)
    inventory=directory/'inventory.json'
    if inventory.exists():info=json.loads(inventory.read_text())
    else:
        status,_,_,raw,_=fetch(BASE+'/sitemap_index.xml')
        if status!=200:raise ValueError('Sitemap index HTTP '+str(status))
        children=[];urls=[]
        for node in ET.fromstring(raw).findall('{*}sitemap/{*}loc'):
            child=node.text
            if urllib.parse.urlsplit(child).netloc!='www.chinamudlogging.com':raise ValueError('Unexpected sitemap host')
            status,_,_,body,_=fetch(child);children.append({'url':child,'status':status})
            if status!=200:raise ValueError('Sitemap child failed')
            for item in ET.fromstring(body).findall('{*}url'):
                urls.append({'url':item.findtext('{*}loc'),'lastmod':item.findtext('{*}lastmod')})
            time.sleep(.8)
        status,_,_,body,_=fetch(BASE+'/robots.txt')
        info={'observedAt':now(),'indexStatus':200,'children':children,'urls':urls,'robots':{'status':status,'body':body.decode()}}
        save(inventory,info)
    queue=list(dict.fromkeys(targets+[r['url'] for r in info['urls']]))
    recordfile=directory/'http-results.json'
    results=json.loads(recordfile.read_text())['results'] if recordfile.exists() else []
    # Preserve first failures, but retry only unresolved URLs after an operator resumes.
    latest={r['url']:r for r in results}
    complete={url for url,r in latest.items() if not r.get('error') and r.get('status') not in [403,429,503]}
    for url in queue:
        if url in complete:continue
        start=time.monotonic()
        try:
            status,final,headers,raw,chain=fetch(url)
            relative='html/'+hashlib.sha256(url.encode()).hexdigest()+'.html'
            path=directory/relative;path.write_bytes(raw);path.chmod(0o600)
            p=Page();p.feed(raw.decode('utf-8',errors='replace'))
            result={'url':url,'observedAt':now(),'status':status,'finalUrl':final,'redirects':chain,'headerRobots':next((v for k,v in headers.items() if k.lower()=='x-robots-tag'),''),'contentType':next((v for k,v in headers.items() if k.lower()=='content-type'),''),'htmlFile':relative,'sha256':hashlib.sha256(raw).hexdigest(),**p.result()}
        except Exception as error:result={'url':url,'observedAt':now(),'error':str(error)}
        results.append(result)
        stopped=result.get('error') or result.get('status') in [403,429,503]
        latest[url]=result
        save(recordfile,{'observedAt':now(),'total':len(queue),'completed':len(latest),'attempts':len(results),'stopped':bool(stopped),'results':results})
        if len(latest)%20==0:print(json.dumps({'completed':len(latest),'total':len(queue)}),flush=True)
        if stopped:raise RuntimeError('Stopped; inspect error/protection before resuming: '+url)
        time.sleep(max(0,.8-(time.monotonic()-start)))
    print(json.dumps({'completed':len(latest),'attempts':len(results),'total':len(queue),'finishedAt':now()}),flush=True)

if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--baseline',required=True,help='Array of URL records with url field')
    parser.add_argument('--out',required=True,help='Private directory outside public source repository')
    args=parser.parse_args()
    capture(pathlib.Path(args.out),[r['url'] for r in json.loads(pathlib.Path(args.baseline).read_text())])
