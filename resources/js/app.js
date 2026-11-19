import './bootstrap';
document.querySelector('.menu')?.addEventListener('click',()=>document.querySelector('#sidebar')?.classList.toggle('open'));
document.querySelectorAll('[data-modal]').forEach(b=>b.addEventListener('click',()=>document.getElementById(b.dataset.modal)?.showModal()));
document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',()=>b.closest('dialog')?.close()));
document.querySelector('.theme')?.addEventListener('click',()=>{
    const next=document.documentElement.dataset.theme==='dark'?'light':'dark';
    document.documentElement.dataset.theme=next;
    localStorage.setItem('mensaliza-theme',next);
});
setTimeout(()=>document.querySelector('.toast')?.remove(),4000);
document.querySelectorAll('input[name="phone"]').forEach(input=>{
    input.placeholder='Ex.: 5511999999999';
    input.title='Informe DDI + DDD + número, somente dígitos';
});
document.querySelectorAll('.customer-grid article').forEach(card=>{
    const profile=card.querySelector('a.entity-link');
    const actions=card.querySelector('.row-actions');
    if(!profile||!actions||actions.querySelector('[data-portal-access]')) return;
    const link=document.createElement('a');
    link.href=profile.href.replace(/\/$/,'')+'/acesso';
    link.textContent='Criar acesso';
    link.dataset.portalAccess='true';
    actions.appendChild(link);
});
