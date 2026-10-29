(() => {
const cfg=window.MSN_CONFIG;
const $=s=>document.querySelector(s);
let activeId=null, lastId=0, pollTimer=null, contactTimer=null, sending=false;
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const avatarHtml=(url,name)=>url?`<img src="${esc(url)}" alt="">`:`<span>${esc((name||'?').trim().charAt(0).toUpperCase())}</span>`;
async function api(action,data={},method='POST'){
 const opts={method,headers:{'X-CSRF-Token':cfg.csrf}};
 let url='api.php?action='+encodeURIComponent(action);
 if(method==='GET'){url+='&'+new URLSearchParams(data).toString();}
 else if(data instanceof FormData){opts.body=data;opts.headers={...opts.headers};}
 else {opts.headers['Content-Type']='application/x-www-form-urlencoded;charset=UTF-8';opts.body=new URLSearchParams(data).toString();}
 const r=await fetch(url,opts); let out;
 try{out=await r.json()}catch{throw new Error('Resposta inválida do servidor.')}
 if(!r.ok||!out.ok)throw new Error(out.error||'Não foi possível concluir a ação.');
 return out;
}
function showHint(text,good=false){$('#addMessage').textContent=text;$('#addMessage').className='hint '+(good?'good':'');}
async function loadContacts(){
 try{
  const out=await api('contacts',{},'GET'), list=$('#contacts');
  $('#contactCount').textContent=out.contacts.length;
  if(!out.contacts.length){list.innerHTML='<div class="empty-contacts">Nenhum contato ainda. Adicione alguém abaixo.</div>';return;}
  list.innerHTML=out.contacts.map(c=>`<button class="contact ${activeId===Number(c.id)?'selected':''}" data-id="${c.id}">
    <div class="avatar">${avatarHtml(c.avatar,c.display_name)}</div><div class="contact-info"><strong>${esc(c.display_name)}</strong><small>${c.online==1?'<i class="online-dot"></i> Online':'Offline'}${c.last_message?' · '+esc(c.last_message):''}</small></div></button>`).join('');
  list.querySelectorAll('.contact').forEach(b=>b.addEventListener('click',()=>openChat(Number(b.dataset.id))));
 }catch(e){showHint(e.message)}
}
async function openChat(id){
 activeId=id;lastId=0;clearInterval(pollTimer);
 $('#welcome').classList.add('hidden');$('#conversation').classList.remove('hidden');
 document.querySelector('.sidebar').classList.add('mobile-hide');
 const out=await api('contacts',{},'GET');const c=out.contacts.find(x=>Number(x.id)===id);
 if(!c){activeId=null;return;}
 $('#chatName').textContent=c.display_name;$('#chatStatus').textContent=c.online==1?'Online':(c.status_message||'Offline');
 $('#chatAvatar').innerHTML=avatarHtml(c.avatar,c.display_name);$('#messages').innerHTML='';
 await loadMessages();pollTimer=setInterval(loadMessages,2200);loadContacts();
 $('#messageInput').focus();
}
async function loadMessages(){
 if(!activeId)return;
 try{
  const out=await api('messages',{with:activeId,after:lastId},'GET');
  $('#chatStatus').textContent=out.contact.online==1?'Online':(out.contact.status_message||'Offline');
  if(out.contact.avatar)$('#chatAvatar').innerHTML=avatarHtml(out.contact.avatar,out.contact.display_name);
  const box=$('#messages'), nearBottom=box.scrollHeight-box.scrollTop-box.clientHeight<100;
  out.messages.forEach(m=>{
   lastId=Math.max(lastId,Number(m.id));
   const node=document.createElement('div');node.className='bubble-row '+(Number(m.sender_id)===cfg.me?'mine':'theirs');
   const bubble=document.createElement('div');bubble.className='bubble';
   const text=document.createElement('div');text.textContent=m.body;
   const time=document.createElement('small');time.textContent=m.time;
   bubble.append(text,time);node.append(bubble);box.append(node);
  });
  if(nearBottom||out.messages.length)box.scrollTop=box.scrollHeight;
 }catch(e){console.error(e)}
}
$('#addForm').addEventListener('submit',async e=>{
 e.preventDefault();const username=$('#addUsername').value.trim();if(!username)return;
 try{const out=await api('add_contact',{username});showHint(out.message,true);$('#addUsername').value='';await loadContacts();}
 catch(err){showHint(err.message)}
});
$('#sendForm').addEventListener('submit',async e=>{
 e.preventDefault();if(!activeId||sending)return;const input=$('#messageInput'),body=input.value.trim();if(!body)return;
 sending=true;try{await api('send',{to:activeId,body});input.value='';await loadMessages();input.focus();}
 catch(err){alert(err.message)}finally{sending=false}
});
$('#messageInput').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();$('#sendForm').requestSubmit()}});
$('#statusForm').addEventListener('submit',async e=>{
 e.preventDefault();try{await api('status',{status:$('#statusMessage').value});showHint('Status atualizado!',true)}catch(err){showHint(err.message)}
});
$('#avatarFile').addEventListener('change',async e=>{
 const f=e.target.files[0];if(!f)return;
 const data=new FormData();data.append('avatar',f);
 try{const out=await api('avatar',data);$('#myAvatar').innerHTML=avatarHtml(out.avatar,'Perfil');await loadContacts();if(activeId)await openChat(activeId);}
 catch(err){alert(err.message)}finally{e.target.value=''}
});
$('#closeChat').addEventListener('click',()=>{activeId=null;clearInterval(pollTimer);$('#conversation').classList.add('hidden');$('#welcome').classList.remove('hidden');document.querySelector('.sidebar').classList.remove('mobile-hide');});
loadContacts();contactTimer=setInterval(loadContacts,12000);
window.addEventListener('beforeunload',()=>{clearInterval(pollTimer);clearInterval(contactTimer)});
})();