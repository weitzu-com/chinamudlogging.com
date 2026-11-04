import ts from 'typescript';
import {readFile,writeFile} from 'node:fs/promises';
const input=new URL('../../src/scripts/funnel.ts',import.meta.url);
const source=await readFile(input,'utf8');
const result=ts.transpileModule(source,{compilerOptions:{target:ts.ScriptTarget.ES2022,module:ts.ModuleKind.CommonJS,removeComments:true}}).outputText;
const body='// Generated from src/scripts/funnel.ts; run wordpress/scripts/build-funnel.mjs.\n(function(exports,CML_FUNNEL_BASE){\n'+result+'\n})(window.CMLFunnel={},"https://chinamudlogging-funnel.kenny-bd6.workers.dev/api/funnel");\n';
await writeFile(new URL('../plugin/cml-core/funnel.js',import.meta.url),body);
