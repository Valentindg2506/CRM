(function() {
    const script = document.currentScript;
    const CRM_URL = script.getAttribute('data-crm-url') || '';
    if (!CRM_URL) return;

    let config = {}, visitorId = '', convId = 0, isOpen = false, hasData = false;

    function getCookie(n) { const m = document.cookie.match(new RegExp('(^| )'+n+'=([^;]+)')); return m ? m[2] : ''; }
    function setCookie(n,v) { document.cookie = n+'='+v+';path=/;max-age=31536000;SameSite=Lax'; }

    visitorId = getCookie('crm_visitor_id');
    if (!visitorId) {
        visitorId = 'v_' + Math.random().toString(36).substr(2,12) + Date.now().toString(36);
        setCookie('crm_visitor_id', visitorId);
    }

    // Inject styles
    const style = document.createElement('style');
    style.textContent = `
    #crm-chat-bubble{position:fixed;z-index:99999;width:60px;height:60px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(0,0,0,0.2);transition:transform 0.2s}
    #crm-chat-bubble:hover{transform:scale(1.1)}
    #crm-chat-bubble svg{width:28px;height:28px;fill:#fff}
    #crm-chat-panel{position:fixed;z-index:99998;width:380px;max-width:calc(100vw - 20px);height:500px;max-height:calc(100vh - 100px);border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,0.15);display:none;flex-direction:column;overflow:hidden;font-family:Inter,sans-serif;background:#fff}
    #crm-chat-panel.open{display:flex}
    .crm-chat-header{padding:20px;color:#fff}
    .crm-chat-header h4{margin:0;font-size:1.1rem;font-weight:700}
    .crm-chat-header p{margin:4px 0 0;font-size:0.8rem;opacity:0.85}
    .crm-chat-body{flex:1;overflow-y:auto;padding:16px;background:#f8fafc}
    .crm-chat-footer{padding:12px;background:#fff;border-top:1px solid #e2e8f0}
    .crm-chat-footer form{display:flex;gap:8px}
    .crm-chat-footer input{flex:1;border:1px solid #e2e8f0;border-radius:24px;padding:8px 16px;font-size:0.9rem;outline:none}
    .crm-chat-footer button{border:none;border-radius:50%;width:40px;height:40px;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center}
    .crm-msg{max-width:80%;padding:10px 14px;border-radius:16px;margin-bottom:8px;font-size:0.88rem;line-height:1.4;word-wrap:break-word}
    .crm-msg.v{background:#e2e8f0;align-self:flex-start;border-bottom-left-radius:4px}
    .crm-msg.a{color:#fff;align-self:flex-end;border-bottom-right-radius:4px;margin-left:auto}
    .crm-msg.s{background:#fef3c7;text-align:center;align-self:center;font-size:0.8rem;border-radius:8px}
    .crm-data-form label{display:block;font-size:0.85rem;font-weight:500;margin-bottom:4px;color:#334155}
    .crm-data-form input{width:100%;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;margin-bottom:10px;font-size:0.9rem}
    .crm-data-form button{width:100%;border:none;color:#fff;padding:10px;border-radius:8px;font-weight:600;cursor:pointer}
    `;
    document.head.appendChild(style);

    // Create bubble
    const bubble = document.createElement('div');
    bubble.id = 'crm-chat-bubble';
    bubble.innerHTML = '<svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>';
    document.body.appendChild(bubble);

    // Create panel
    const panel = document.createElement('div');
    panel.id = 'crm-chat-panel';
    document.body.appendChild(panel);

    function applyPosition(pos, color) {
        const s = pos === 'bottom-left' ? 'left' : 'right';
        bubble.style[s] = '20px'; bubble.style.bottom = '20px'; bubble.style.background = color;
        panel.style[s] = '20px'; panel.style.bottom = '90px';
    }

    function renderPanel() {
        const c = config;
        panel.innerHTML = `
        <div class="crm-chat-header" style="background:${c.color_primario||'#10b981'}">
            <div style="display:flex;justify-content:space-between;align-items:start">
                <div><h4>${c.titulo||'Chat'}</h4><p>${c.subtitulo||''}</p></div>
                <span style="cursor:pointer;font-size:1.2rem" onclick="document.getElementById('crm-chat-panel').classList.remove('open')">&times;</span>
            </div>
        </div>
        <div class="crm-chat-body" id="crm-chat-body" style="display:flex;flex-direction:column"></div>
        <div class="crm-chat-footer" id="crm-chat-footer"></div>`;

        if (c.pedir_datos == 1 && !hasData) {
            showDataForm();
        } else {
            initChat();
        }
    }

    function showDataForm() {
        const body = document.getElementById('crm-chat-body');
        const color = config.color_primario || '#10b981';
        body.innerHTML = `<div class="crm-data-form" style="padding:10px">
            <p style="font-size:0.9rem;color:#64748b;margin-bottom:16px">Para comenzar, necesitamos algunos datos:</p>
            <label>Nombre *</label><input type="text" id="crm-d-nombre" required>
            <label>Email</label><input type="email" id="crm-d-email">
            <label>Telefono</label><input type="tel" id="crm-d-tel">
            <button style="background:${color}" onclick="crmStartChat()">Iniciar chat</button>
        </div>`;
        document.getElementById('crm-chat-footer').innerHTML = '';
    }

    window.crmStartChat = function() {
        const nombre = document.getElementById('crm-d-nombre').value.trim();
        if (!nombre) { alert('El nombre es obligatorio'); return; }
        const email = document.getElementById('crm-d-email').value.trim();
        const tel = document.getElementById('crm-d-tel').value.trim();
        hasData = true;

        fetch(CRM_URL + '/api/chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=init&visitor_id='+encodeURIComponent(visitorId)+'&nombre='+encodeURIComponent(nombre)+'&email='+encodeURIComponent(email)+'&telefono='+encodeURIComponent(tel)+'&pagina='+encodeURIComponent(location.href)
        }).then(r=>r.json()).then(d => {
            if (d.success) { convId = d.conversacion_id; initChat(); }
        });
    };

    function initChat() {
        if (!convId) {
            fetch(CRM_URL + '/api/chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=init&visitor_id='+encodeURIComponent(visitorId)+'&pagina='+encodeURIComponent(location.href)
            }).then(r=>r.json()).then(d => { if (d.success) { convId = d.conversacion_id; loadMessages(); } });
        } else {
            loadMessages();
        }
        const color = config.color_primario || '#10b981';
        document.getElementById('crm-chat-footer').innerHTML = `<form onsubmit="crmSendMsg(event)">
            <input type="text" id="crm-msg-input" placeholder="Escribe un mensaje..." autocomplete="off">
            <button style="background:${color}"><svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg></button>
        </form>`;
    }

    function loadMessages() {
        fetch(CRM_URL + '/api/chat.php?action=messages&visitor_id=' + encodeURIComponent(visitorId))
        .then(r=>r.json()).then(d => {
            if (!d.success) return;
            const body = document.getElementById('crm-chat-body');
            body.innerHTML = '';
            d.messages.forEach(m => {
                const cls = m.emisor === 'visitante' ? 'v' : (m.emisor === 'agente' ? 'a' : 's');
                const div = document.createElement('div');
                div.className = 'crm-msg ' + cls;
                if (cls === 'a') div.style.background = config.color_primario || '#10b981';
                div.textContent = m.mensaje;
                body.appendChild(div);
            });
            body.scrollTop = body.scrollHeight;
        });
    }

    window.crmSendMsg = function(e) {
        e.preventDefault();
        const input = document.getElementById('crm-msg-input');
        const msg = input.value.trim();
        if (!msg) return;
        input.value = '';
        fetch(CRM_URL + '/api/chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=send&visitor_id='+encodeURIComponent(visitorId)+'&mensaje='+encodeURIComponent(msg)
        }).then(r=>r.json()).then(() => loadMessages());
    };

    // Init
    fetch(CRM_URL + '/api/chat.php?action=config').then(r=>r.json()).then(d => {
        if (!d.success || !d.config || d.config.activo != 1) { bubble.style.display = 'none'; return; }
        config = d.config;
        applyPosition(config.posicion, config.color_primario || '#10b981');
        renderPanel();
    });

    bubble.addEventListener('click', () => {
        isOpen = !isOpen;
        panel.classList.toggle('open', isOpen);
        if (isOpen && convId) loadMessages();
    });

    // Poll for new messages
    setInterval(() => { if (isOpen && convId) loadMessages(); }, 5000);
})();
