// Input: privately captured, current public sitemap inventory. No network or deployment.
import {readFile,writeFile} from 'node:fs/promises';
const input=process.argv[2];
if(!input)throw new Error('Usage: node wordpress/scripts/update-measurement-paths.mjs INVENTORY.json [--check]');
const inventory=JSON.parse(await readFile(input,'utf8'));
if(!Array.isArray(inventory.urls)||inventory.urls.length===0)throw new Error('Nonempty urls array required');
const paths=[...new Set(inventory.urls.map(value=>{
 const u=new URL(value);
 if(u.origin!=='https://www.chinamudlogging.com'||u.search||u.hash||!/^\/(?:[a-z0-9-]+\/)*$/.test(u.pathname)||value!==u.href)throw new Error('Unexpected public URL');
 return u.pathname;
}))].sort();
const target=new URL('../../workers/funnel/published-paths.json',import.meta.url);
if(process.argv.includes('--check')){
 const old=JSON.parse(await readFile(target,'utf8'));
 const missing=paths.filter(p=>!old.paths.includes(p)),removed=old.paths.filter(p=>!paths.includes(p));
 console.log(JSON.stringify({current:paths.length,missing,removed},null,2));
 if(missing.length||removed.length)process.exitCode=1;
}else{
 await writeFile(target,JSON.stringify({observedAt:inventory.checkedAt,source:'https://www.chinamudlogging.com/sitemap_index.xml',paths},null,2)+'\n');
 console.log(JSON.stringify({paths:paths.length}));
}
