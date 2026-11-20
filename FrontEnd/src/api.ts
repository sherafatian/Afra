let token='';let csrf='';
export function setCredentials(t:string,c:string){token=t;csrf=c;}
export class ApiError extends Error {constructor(public status:number,message:string,public fields:Record<string,string[]>={}){super(message);}}
let renewal:Promise<void>|null=null;
export async function api<T=any>(path:string,options:RequestInit={},retry=true):Promise<T>{
 const response=await fetch('/api/'+path,{...options,credentials:'include',headers:{Accept:'application/json',...(options.body instanceof FormData?{}:{'Content-Type':'application/json'}),...(token?{Authorization:'Bearer '+token}:{}),...options.headers}});
 if(response.status===401 && retry && !path.startsWith('auth/')){
  renewal??=fetch('/api/auth/refresh',{method:'POST',credentials:'include',headers:{'X-CSRF-Token':csrf}}).then(async r=>{if(!r.ok)throw new ApiError(401,'Please sign in again.');const d=(await r.json()).data;setCredentials(d.access_token,d.csrf_token);}).finally(()=>{renewal=null;});await renewal;return api(path,options,false);
 }
 const body=await response.json();if(!response.ok)throw new ApiError(response.status,body.message||body.error?.message||'Request failed',body.errors||{});return body.data;
}
export function send<T=any>(path:string,data:unknown,method='POST',version?:number){return api<T>(path,{method,body:JSON.stringify(data),headers:version?{'If-Match':String(version)}:{}});}
export async function download(path:string):Promise<Blob>{
 let response=await fetch('/api/'+path,{credentials:'include',headers:{Authorization:'Bearer '+token}});
 if(response.status===401){await api('v1/me');response=await fetch('/api/'+path,{credentials:'include',headers:{Authorization:'Bearer '+token}});}
 if(!response.ok){let message='Download failed';try{message=(await response.json()).message||message;}catch{/* Response may be an upstream error page. */}throw new ApiError(response.status,message);}
 return response.blob();
}
