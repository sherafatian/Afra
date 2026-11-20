import {afterEach,describe,expect,it,vi} from 'vitest';
afterEach(()=>{vi.unstubAllGlobals();vi.resetModules();});
describe('API boundary',()=>{
 it('keeps validation field details available to the editor',async()=>{
  vi.stubGlobal('fetch',vi.fn(async()=>new Response(JSON.stringify({message:'Invalid input',errors:{title:['A title is required.']}}),{status:422})));
  const {api,ApiError}=await import('./api');
  try{await api('v1/projects/p/cases');throw new Error('Expected validation failure');}catch(error){expect(error).toBeInstanceOf(ApiError);expect((error as InstanceType<typeof ApiError>).fields.title).toEqual(['A title is required.']);}
 });
 it('uses a single refresh for simultaneous expired requests, then retries both',async()=>{
  let refreshCount=0;let requestCount=0;
  vi.stubGlobal('fetch',vi.fn(async(url:string,options:RequestInit)=>{
   if(url.endsWith('/auth/refresh')){refreshCount++;await new Promise(resolve=>setTimeout(resolve,10));return new Response(JSON.stringify({data:{access_token:'renewed',csrf_token:'next-nonce'}}),{status:200});}
   requestCount++;const headers=options.headers as Record<string,string>;
   return headers.Authorization==='Bearer renewed'?new Response(JSON.stringify({data:{ok:true}}),{status:200}):new Response(JSON.stringify({message:'Expired'}),{status:401});
  }));
  const {api,setCredentials}=await import('./api');setCredentials('expired','nonce');
  expect(await Promise.all([api('v1/me'),api('v1/projects')])).toEqual([{ok:true},{ok:true}]);expect(refreshCount).toBe(1);expect(requestCount).toBe(4);
 });
});
