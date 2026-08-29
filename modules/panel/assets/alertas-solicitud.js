(() => {
  'use strict';

  const SONIDO_POR_DEFECTO = 'alerta01.mp3';
  const TOTAL_SONIDOS = 20;
  const CLAVE_LOCALSTORAGE = 'taxis_sonido_alerta';
  const MODAL_AUTO_CIERRE_MS = 20000;
  const POLL_MS = 4000;
  const BARRA_OCULTA_MS = 30000;

  function sonidoElegido() {
    try {
      return localStorage.getItem(CLAVE_LOCALSTORAGE) || SONIDO_POR_DEFECTO;
    } catch (_) {
      return SONIDO_POR_DEFECTO;
    }
  }

  function guardarSonidoElegido(archivo) {
    try {
      localStorage.setItem(CLAVE_LOCALSTORAGE, archivo);
    } catch (_) {
      // localStorage no disponible (privado/bloqueado) — el sonido por
      // defecto sigue funcionando, solo no se recuerda entre sesiones.
    }
  }

  function reproducirSonido(archivo) {
    try {
      const audio = new Audio('/sounds/' + archivo);
      audio.volume = 0.85;
      audio.play().catch(() => {});
    } catch (_) {}
  }

  function vibrar() {
    try {
      if (navigator.vibrate) navigator.vibrate([200, 100, 200, 100, 300]);
    } catch (_) {}
  }

  const vistos = new Set();
  let primed = false;
  const colaModal = [];
  let modalAbierto = false;
  let cierreAutomaticoTimer = null;
  let barraOculta = false;
  let barraOcultaTimer = null;

  function crearDom() {
    if (document.getElementById('solicitud-alertas-bar')) return;

    const estilos = document.createElement('style');
    estilos.textContent = [
      '@keyframes saPop{from{transform:translate(-50%,-50%) scale(.7);opacity:0}to{transform:translate(-50%,-50%) scale(1);opacity:1}}',
      '@keyframes saGlow{0%,100%{box-shadow:0 0 30px rgba(250,204,21,.55),0 0 60px rgba(250,204,21,.25)}50%{box-shadow:0 0 55px rgba(250,204,21,.85),0 0 100px rgba(250,204,21,.4)}}',
      '@keyframes saShake{0%,100%{transform:translateX(0)}10%,30%,50%,70%,90%{transform:translateX(-4px)}20%,40%,60%,80%{transform:translateX(4px)}}',
      '@keyframes saBarPulse{0%,100%{opacity:1}50%{opacity:.75}}',
      '#solicitud-alertas-bar{animation:saBarPulse 2s ease-in-out infinite}',
      '#solicitud-alertas-bar:hover{opacity:1!important}',
      '#solicitud-alertas-modal .sa-caja{animation:saPop .35s ease-out,saGlow 1.4s ease-in-out infinite,saShake .5s ease-in-out 1}',
      '@media (prefers-reduced-motion: reduce){#solicitud-alertas-bar,#solicitud-alertas-modal .sa-caja{animation:none!important}}',
    ].join('\n');
    document.head.appendChild(estilos);

    const bar = document.createElement('div');
    bar.id = 'solicitud-alertas-bar';
    bar.style.cssText = 'display:none;position:fixed;top:0;left:0;right:0;z-index:99998;padding:10px 20px;text-align:center;cursor:pointer;font-family:inherit;font-size:14px;font-weight:700;letter-spacing:.3px;background:linear-gradient(135deg,#facc15,#eab308);color:#0b0b0c;border-bottom:2px solid #0b0b0c';
    bar.innerHTML = '<span id="sa-bar-texto">Solicitudes en cola</span> <span id="sa-bar-count" style="background:rgba(11,11,12,.18);padding:2px 10px;border-radius:12px;margin-left:8px;font-weight:800"></span>';
    document.body.appendChild(bar);

    const modal = document.createElement('div');
    modal.id = 'solicitud-alertas-modal';
    modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.82);backdrop-filter:blur(6px)';
    modal.innerHTML =
      '<div class="sa-caja" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#161616;border:3px solid #facc15;border-radius:24px;padding:40px 32px;width:70vw;min-height:35vh;max-width:640px;box-sizing:border-box;text-align:center;font-family:inherit;display:flex;flex-direction:column;justify-content:center;align-items:center">' +
      '<div style="font-size:clamp(16px,2.4vw,22px);letter-spacing:2px;text-transform:uppercase;color:#facc15;font-weight:800;margin-bottom:16px">Nueva solicitud</div>' +
      '<p id="sa-modal-cliente" style="color:#fff;font-size:clamp(22px,4vw,34px);font-weight:700;margin:0 0 12px"></p>' +
      '<p id="sa-modal-detalle" style="color:#d4d4d4;font-size:clamp(15px,2vw,19px);margin:0 0 28px;line-height:1.5"></p>' +
      '<div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">' +
      '<button id="sa-modal-ver" type="button" style="background:#facc15;color:#0b0b0c;border:none;padding:14px 34px;border-radius:12px;font-size:clamp(14px,1.6vw,17px);font-weight:700;cursor:pointer">Ver</button>' +
      '<button id="sa-modal-cerrar" type="button" style="background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.25);padding:14px 34px;border-radius:12px;font-size:clamp(14px,1.6vw,17px);cursor:pointer">Cerrar</button>' +
      '</div></div>';
    document.body.appendChild(modal);

    const btnSonido = document.createElement('button');
    btnSonido.id = 'solicitud-alertas-btn-sonido';
    btnSonido.type = 'button';
    btnSonido.title = 'Sonido de alerta';
    btnSonido.setAttribute('aria-label', 'Elegir sonido de alerta');
    btnSonido.style.cssText = 'position:fixed;bottom:18px;right:18px;z-index:99997;width:46px;height:46px;border-radius:999px;background:#161616;border:2px solid #facc15;color:#facc15;cursor:pointer;display:flex;align-items:center;justify-content:center';
    btnSonido.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5Z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>';
    document.body.appendChild(btnSonido);

    const panelSonido = document.createElement('div');
    panelSonido.id = 'solicitud-alertas-panel-sonido';
    panelSonido.style.cssText = 'display:none;position:fixed;bottom:72px;right:18px;z-index:99997;background:#161616;border:1px solid #3a3a3a;border-radius:14px;padding:14px;width:230px;font-family:inherit;box-shadow:0 10px 40px rgba(0,0,0,.5)';
    let opciones = '';
    for (let i = 1; i <= TOTAL_SONIDOS; i++) {
      const archivo = 'alerta' + String(i).padStart(2, '0') + '.mp3';
      opciones += '<option value="' + archivo + '">Alerta ' + i + '</option>';
    }
    panelSonido.innerHTML =
      '<label for="sa-select-sonido" style="display:block;font-size:12px;color:#a3a3a3;margin-bottom:6px">Sonido de alerta</label>' +
      '<select id="sa-select-sonido" style="width:100%;background:#1c1c1c;border:1px solid #3a3a3a;color:#fafafa;border-radius:8px;padding:6px 8px;font-size:13px;margin-bottom:8px">' + opciones + '</select>' +
      '<button id="sa-btn-probar" type="button" style="width:100%;background:#facc15;color:#0b0b0c;border:none;padding:8px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Probar sonido</button>';
    document.body.appendChild(panelSonido);

    const selectSonido = panelSonido.querySelector('#sa-select-sonido');
    selectSonido.value = sonidoElegido();
    selectSonido.addEventListener('change', () => {
      guardarSonidoElegido(selectSonido.value);
      reproducirSonido(selectSonido.value);
    });
    panelSonido.querySelector('#sa-btn-probar').addEventListener('click', () => {
      reproducirSonido(selectSonido.value);
    });

    btnSonido.addEventListener('click', (evento) => {
      evento.stopPropagation();
      panelSonido.style.display = panelSonido.style.display === 'none' ? 'block' : 'none';
    });
    document.addEventListener('click', (evento) => {
      if (!panelSonido.contains(evento.target) && evento.target !== btnSonido && !btnSonido.contains(evento.target)) {
        panelSonido.style.display = 'none';
      }
    });

    bar.addEventListener('click', () => {
      bar.style.display = 'none';
      barraOculta = true;
      if (barraOcultaTimer) clearTimeout(barraOcultaTimer);
      barraOcultaTimer = setTimeout(() => {
        barraOculta = false;
      }, BARRA_OCULTA_MS);
      if (!modalAbierto && colaModal.length > 0) mostrarSiguienteModal();
    });

    modal.querySelector('#sa-modal-cerrar').addEventListener('click', cerrarModal);
    modal.querySelector('#sa-modal-ver').addEventListener('click', () => {
      cerrarModal();
      if (window.location.pathname !== '/modules/panel/index.php') {
        window.location.href = '/modules/panel/index.php';
      } else {
        const cola = document.getElementById('cola');
        if (cola) cola.scrollIntoView({ behavior: 'smooth' });
      }
    });
  }

  function cerrarModal() {
    const modal = document.getElementById('solicitud-alertas-modal');
    if (modal) modal.style.display = 'none';
    modalAbierto = false;
    if (cierreAutomaticoTimer) {
      clearTimeout(cierreAutomaticoTimer);
      cierreAutomaticoTimer = null;
    }
    if (colaModal.length > 0) mostrarSiguienteModal();
  }

  function mostrarSiguienteModal() {
    const item = colaModal.shift();
    if (!item) return;
    modalAbierto = true;
    document.getElementById('sa-modal-cliente').textContent = item.cliente_nombre || item.cliente_whatsapp || 'Cliente';
    let detalle = item.recogida_texto || '';
    if (item.destino_texto) detalle += ' → ' + item.destino_texto;
    document.getElementById('sa-modal-detalle').textContent = detalle;
    document.getElementById('solicitud-alertas-modal').style.display = 'block';
    reproducirSonido(sonidoElegido());
    vibrar();
    cierreAutomaticoTimer = setTimeout(() => {
      if (modalAbierto) cerrarModal();
    }, MODAL_AUTO_CIERRE_MS);
  }

  function actualizarBarra(sinAtender) {
    const bar = document.getElementById('solicitud-alertas-bar');
    if (!bar) return;
    if (sinAtender.length === 0) {
      bar.style.display = 'none';
      return;
    }
    document.getElementById('sa-bar-count').textContent = String(sinAtender.length);
    if (!barraOculta) bar.style.display = 'block';
  }

  const SolicitudAlertas = {
    procesar(lista) {
      crearDom();
      const pendientes = lista || [];
      // La barra solo debe insistir con lo que todavía no tiene vehículo
      // asignado — una vez atendida (asignada), ya no es "nueva" y no debe
      // seguir apareciendo arriba aunque siga en curso.
      const sinAtender = pendientes.filter((item) => !item.vehiculo_id);
      actualizarBarra(sinAtender);

      const idsActuales = new Set(pendientes.map((item) => item.id));

      // La primera pasada tras cargar la página solo establece la línea
      // base — si no, cada carga dispara el modal de todo lo que ya
      // estaba en cola desde antes.
      if (!primed) {
        for (const id of idsActuales) vistos.add(id);
        primed = true;
        return;
      }

      for (const item of pendientes) {
        if (!vistos.has(item.id)) {
          vistos.add(item.id);
          colaModal.push(item);
        }
      }

      if (!modalAbierto && colaModal.length > 0) {
        mostrarSiguienteModal();
      }
    },
  };

  window.SolicitudAlertas = SolicitudAlertas;

  const endpointAutoPoll = document.currentScript && document.currentScript.dataset.autopollEndpoint;
  if (endpointAutoPoll) {
    const consultar = () => {
      fetch(endpointAutoPoll, { credentials: 'same-origin' })
        .then((respuesta) => (respuesta.ok ? respuesta.json() : { ok: false }))
        .then((datos) => {
          if (datos && datos.ok) SolicitudAlertas.procesar(datos.solicitudes || []);
        })
        .catch(() => {});
    };
    consultar();
    setInterval(consultar, POLL_MS);
  }
})();
