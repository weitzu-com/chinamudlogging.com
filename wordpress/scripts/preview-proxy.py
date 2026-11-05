#!/usr/bin/env python3
"""Loopback-only visual QA of protected SiteGround; upstream TLS stays verified.

The staging password stays in a local 0600 file and is never embedded in browser
URLs, screenshots, source files, or command arguments. No external origins can
be requested and redirects cannot carry authorization to another hostname.
"""
import base64,json,re,urllib.request,urllib.error
from http.server import BaseHTTPRequestHandler,ThreadingHTTPServer
from pathlib import Path
UPSTREAM='https://preview.chinamudlogging.com';LOCAL='http://127.0.0.1:9402'
auth=json.loads((Path.home()/'.cache/chinamudlogging-wordpress/preview-access.json').read_text())
authorization='Basic '+base64.b64encode((auth['user']+':'+auth['password']).encode()).decode()
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
opener=urllib.request.build_opener(NoRedirect)
class Handler(BaseHTTPRequestHandler):
 def do_GET(self):self.forward()
 def do_POST(self):self.forward()
 def log_message(self,*args):pass
 def forward(self):
  if self.command=='GET' and self.path=='/acceptance-start':
   ticket=json.loads((Path.home()/'.cache/chinamudlogging-wordpress/results/funnel-test-ticket.json').read_text())
   if not re.fullmatch(r'[a-f0-9-]{36}',ticket['id']) or not re.fullmatch(r'[a-f0-9]{64}',ticket['token']):self.send_error(400);return
   self.send_response(302);self.send_header('Location','https://www.chinamudlogging.com/?funnel_test='+ticket['id']+'.'+ticket['token']);self.send_header('Cache-Control','no-store');self.end_headers();return
  if not self.path.startswith('/') or self.path.startswith('//'):
   self.send_error(400);return
  length=int(self.headers.get('Content-Length','0'))
  if length>65536:self.send_error(413);return
  headers={'Authorization':authorization,'User-Agent':'ChinaMudLogging-Visual-QA/1.0','Accept-Encoding':'identity'}
  for name in ['Content-Type','Accept','Cookie']:
   if self.headers.get(name):headers[name]=self.headers[name]
  if self.headers.get('Origin'):headers['Origin']=UPSTREAM
  body=self.rfile.read(length) if self.command=='POST' else None
  request=urllib.request.Request(UPSTREAM+self.path,data=body,headers=headers,method=self.command)
  try:
   try:response=opener.open(request,timeout=40)
   except urllib.error.HTTPError as error:response=error
   data=response.read();content_type=response.headers.get('Content-Type','')
   if 'text/html' in content_type:data=data.replace(UPSTREAM.encode(),LOCAL.encode())
   self.send_response(response.status)
   for name,value in response.headers.items():
    if name.lower() in ['content-length','transfer-encoding','connection','content-encoding']:continue
    if name.lower()=='location':
     if value.startswith(UPSTREAM):value=value.replace(UPSTREAM,LOCAL,1)
     elif value.startswith('http://') or value.startswith('https://'):continue
    self.send_header(name,value)
   self.send_header('Cache-Control','no-store, private');self.send_header('Content-Length',str(len(data)));self.end_headers();self.wfile.write(data)
  except Exception:self.send_error(502,'Protected upstream preview unavailable')
if __name__=='__main__':
 print('SiteGround visual preview: '+LOCAL,flush=True)
 ThreadingHTTPServer(('127.0.0.1',9402),Handler).serve_forever()
