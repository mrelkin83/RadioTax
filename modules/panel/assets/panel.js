(() => {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const usuarioIdActual = parseInt(document.querySelector('meta[name="usuario-id"]').content, 10);
  const usuarioRolActual = document.querySelector('meta[name="usuario-rol"]').content;
  const POLL_MS = 4000;

  // Colores de estado: un punto real (.punto-estado, ver _tema.php), nunca un
  // emoji como icono — un emoji no es un componente de UI consistente entre
  // sistemas operativos ni tiene contraste garantizado.
  const ESTADOS_VEHICULO = {
    DISPONIBLE: { color: '#22C55E', texto: 'Disponible' },
    EN_TURNO: { color: '#22C55E', texto: 'En turno' },
    SOLICITADO: { color: '#F59E0B', texto: 'Solicitado' },
    PENDIENTE_CONFIRMACION: { color: '#F59E0B', texto: 'Pendiente confirmación' },
    EN_SERVICIO: { color: '#38BDF8', texto: 'En servicio' },
    FUERA_DE_TURNO: { color: '#64748B', texto: 'Fuera de turno' },
    NO_DISPONIBLE: { color: '#EF4444', texto: 'No disponible' },
  };

  const ESTADOS_CARRERA = {
    RECIBIDA: { color: '#94A3B8', texto: 'Recibida' },
    DATOS_COMPLETOS: { color: '#94A3B8', texto: 'Datos completos' },
    EN_DESPACHO: { color: '#F59E0B', texto: 'En despacho' },
    CANDIDATOS_PROPUESTOS: { color: '#F59E0B', texto: 'Candidatos propuestos' },
    ASIGNADA: { color: '#F59E0B', texto: 'Asignada' },
    ACEPTADA: { color: '#F59E0B', texto: 'Aceptada' },
    EN_CAMINO: { color: '#F59E0B', texto: 'En camino' },
    EN_SERVICIO: { color: '#38BDF8', texto: 'En servicio' },
    FINALIZADA: { color: '#22C55E', texto: 'Finalizada' },
    CANCELADA: { color: '#EF4444', texto: 'Cancelada' },
    NO_ATENDIDA: { color: '#EF4444', texto: 'No atendida' },
  };

  function el(tag, opts = {}) {
    const nodo = document.createElement(tag);
    if (opts.className) nodo.className = opts.className;
    if (opts.text !== undefined) nodo.textContent = opts.text;
    if (opts.attrs) {
      for (const [clave, valor] of Object.entries(opts.attrs)) {
        nodo.setAttribute(clave, valor);
      }
    }
    return nodo;
  }

  /** Punto de color + texto, en vez de un emoji como indicador de estado. */
  function insigniaEstado(color, texto, claseTexto = 'text-xs text-slate-300') {
    const envoltorio = el('span', { className: 'inline-flex items-center gap-1.5' });
    const punto = el('span', { className: 'punto-estado' });
    punto.style.background = color;
    envoltorio.appendChild(punto);
    envoltorio.appendChild(el('span', { className: claseTexto, text: texto }));
    return envoltorio;
  }

  /** Icono de ubicación (recogida), inline SVG — nunca un emoji. */
  function iconoPin() {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('class', 'w-3.5 h-3.5 text-slate-500 shrink-0 mt-0.5');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    svg.setAttribute('aria-hidden', 'true');
    svg.innerHTML = '<path d="M12 21s-7-6.5-7-11.5a7 7 0 0 1 14 0C19 14.5 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>';
    return svg;
  }

  /** Icono de destino (bandera), inline SVG — nunca un emoji. */
  function iconoBandera() {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('class', 'w-3.5 h-3.5 text-slate-500 shrink-0 mt-0.5');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    svg.setAttribute('aria-hidden', 'true');
    svg.innerHTML = '<path d="M5 21V4"/><path d="M5 4h13l-2.5 4L18 12H5"/>';
    return svg;
  }

  /** Fila con icono + texto, para recogida/destino. */
  function filaConIcono(icono, texto) {
    const fila = el('div', { className: 'flex items-start gap-1.5' });
    fila.appendChild(icono);
    fila.appendChild(el('span', { className: 'text-slate-300 text-sm', text: texto }));
    return fila;
  }

  async function api(ruta, opciones = {}) {
    const respuesta = await fetch(ruta, {
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      ...opciones,
    });
    const cuerpo = await respuesta.json().catch(() => ({}));
    if (!respuesta.ok) {
      throw new Error(cuerpo.error || `Error ${respuesta.status}`);
    }
    return cuerpo;
  }

  function tiempoDesde(fechaSql) {
    const ms = Date.now() - new Date(fechaSql.replace(' ', 'T')).getTime();
    const min = Math.max(0, Math.round(ms / 60000));
    return min < 60 ? `hace ${min} min` : `hace ${Math.round(min / 60)} h`;
  }

  let vehiculosDisponiblesCache = [];
  let conductoresCache = [];

  function tarjetaCarrera(carrera) {
    const tarjeta = el('div', { className: 'rounded-xl bg-card border border-border p-4', attrs: { 'data-carrera-id': carrera.id } });

    const cabecera = el('div', { className: 'flex justify-between items-center mb-2' });
    const estadoInfo = ESTADOS_CARRERA[carrera.estado] || { color: '#64748B', texto: carrera.estado };
    const insignia = insigniaEstado(estadoInfo.color, `#${carrera.id} · ${estadoInfo.texto}`, 'font-mono text-xs text-slate-400');
    cabecera.appendChild(insignia);
    cabecera.appendChild(el('span', { className: 'text-xs text-slate-500', text: tiempoDesde(carrera.creado_en) }));
    tarjeta.appendChild(cabecera);

    tarjeta.appendChild(el('p', { className: 'text-foreground font-medium mb-1.5', text: carrera.cliente_nombre || carrera.cliente_whatsapp }));
    tarjeta.appendChild(filaConIcono(iconoPin(), carrera.recogida_texto));
    tarjeta.appendChild(filaConIcono(iconoBandera(), carrera.destino_texto));

    if (carrera.observaciones) {
      tarjeta.appendChild(el('p', { className: 'text-slate-500 text-xs mt-1.5', text: carrera.observaciones }));
    }

    const acciones = el('div', { className: 'mt-3 flex flex-wrap items-center gap-2' });

    const asignable = !['ASIGNADA', 'ACEPTADA', 'EN_CAMINO', 'EN_SERVICIO'].includes(carrera.estado);
    if (asignable) {
      const select = el('select', { className: 'bg-muted border border-border text-foreground text-sm rounded-lg px-2 py-1.5' });
      select.appendChild(el('option', { text: 'Vehículo…', attrs: { value: '' } }));
      for (const v of vehiculosDisponiblesCache) {
        select.appendChild(el('option', { text: `${v.numero_interno} · ${v.placa}`, attrs: { value: v.id } }));
      }
      const btnAsignar = el('button', { className: 'text-sm font-medium bg-accent hover:bg-accent-hover text-on-accent px-3 py-1.5 rounded-lg', text: 'Asignar' });
      btnAsignar.addEventListener('click', async () => {
        if (!select.value) return;
        btnAsignar.disabled = true;
        try {
          await api('/modules/panel/api/asignar.php', { method: 'POST', body: JSON.stringify({ carrera_id: carrera.id, vehiculo_id: Number(select.value) }) });
          await refrescar({ forzar: true });
        } catch (e) {
          alert(e.message);
          btnAsignar.disabled = false;
        }
      });
      acciones.appendChild(select);
      acciones.appendChild(btnAsignar);
    }

    const transicionesCarrera = { ASIGNADA: 'EN_CAMINO', EN_CAMINO: 'EN_SERVICIO', EN_SERVICIO: 'FINALIZADA' };
    const etiquetasTransicion = { EN_CAMINO: 'En camino', EN_SERVICIO: 'En servicio', FINALIZADA: 'Finalizar' };
    const destino = transicionesCarrera[carrera.estado];
    if (destino) {
      const btnAvanzar = el('button', { className: 'text-sm font-medium bg-accent hover:bg-accent-hover text-on-accent px-3 py-1.5 rounded-lg', text: etiquetasTransicion[destino] });
      btnAvanzar.addEventListener('click', async () => {
        btnAvanzar.disabled = true;
        try {
          await api('/modules/panel/api/avanzar_estado.php', { method: 'POST', body: JSON.stringify({ carrera_id: carrera.id, estado: destino }) });
          await refrescar({ forzar: true });
        } catch (e) {
          alert(e.message);
          btnAvanzar.disabled = false;
        }
      });
      acciones.appendChild(btnAvanzar);
    }

    // Cerrar la carrera directo, sin pasar por cada paso — para cuando el
    // radiooperador ya sabe (por radio) que el servicio terminó. EN_SERVICIO
    // ya tiene su propio botón "Finalizar" arriba, así que no se duplica acá.
    if (carrera.estado !== 'EN_SERVICIO') {
      const btnFinalizarManual = el('button', { className: 'text-sm font-medium bg-muted hover:bg-secondary text-slate-200 px-3 py-1.5 rounded-lg', text: 'Finalizar manualmente' });
      btnFinalizarManual.addEventListener('click', async () => {
        if (!confirm('¿Marcar esta carrera como finalizada? Se salta los pasos intermedios.')) return;
        btnFinalizarManual.disabled = true;
        try {
          await api('/modules/panel/api/avanzar_estado.php', { method: 'POST', body: JSON.stringify({ carrera_id: carrera.id, estado: 'FINALIZADA' }) });
          await refrescar({ forzar: true });
        } catch (e) {
          alert(e.message);
          btnFinalizarManual.disabled = false;
        }
      });
      acciones.appendChild(btnFinalizarManual);
    }

    // Una carrera con el cliente ya a bordo (EN_SERVICIO) no se cancela: de
    // ahí solo se sale finalizándola (regla del ciclo, §6 del system prompt maestro).
    if (carrera.estado !== 'EN_SERVICIO') {
      const inputMotivo = el('input', { className: 'bg-muted border border-border text-foreground placeholder:text-slate-500 text-sm rounded-lg px-2.5 py-1.5 w-32', attrs: { placeholder: 'Motivo' } });
      const btnCancelar = el('button', { className: 'text-sm font-medium bg-destructive/15 hover:bg-destructive/25 text-red-300 px-3 py-1.5 rounded-lg', text: 'Cancelar' });
      btnCancelar.addEventListener('click', async () => {
        if (!inputMotivo.value.trim()) {
          inputMotivo.focus();
          return;
        }
        btnCancelar.disabled = true;
        try {
          await api('/modules/panel/api/cancelar.php', { method: 'POST', body: JSON.stringify({ carrera_id: carrera.id, motivo: inputMotivo.value.trim() }) });
          await refrescar({ forzar: true });
        } catch (e) {
          alert(e.message);
          btnCancelar.disabled = false;
        }
      });
      acciones.appendChild(inputMotivo);
      acciones.appendChild(btnCancelar);
    }

    tarjeta.appendChild(acciones);
    return tarjeta;
  }

  function filaFinalizada(carrera) {
    const estadoInfo = ESTADOS_CARRERA[carrera.estado] || { color: '#64748B', texto: carrera.estado };
    const fila = el('div', { className: 'flex justify-between items-center text-sm bg-card/60 border border-border rounded-lg px-3 py-2' });
    fila.appendChild(el('span', { className: 'font-mono text-slate-300', text: `#${carrera.id} · ${carrera.cliente_nombre || carrera.cliente_whatsapp}` }));
    fila.appendChild(insigniaEstado(estadoInfo.color, estadoInfo.texto));
    return fila;
  }

  function tarjetaVehiculo(v) {
    const info = ESTADOS_VEHICULO[v.estado_vehiculo] || { color: '#64748B', texto: v.estado_vehiculo };
    const tarjeta = el('div', { className: 'rounded-xl bg-card border border-border p-3' });

    const cabecera = el('div', { className: 'flex justify-between items-center' });
    const punto = el('span', { className: 'punto-estado' });
    punto.style.background = info.color;
    const nombreVehiculo = el('span', { className: 'inline-flex items-center gap-1.5 text-foreground font-medium font-mono text-sm' });
    nombreVehiculo.appendChild(punto);
    nombreVehiculo.appendChild(document.createTextNode(`${v.numero_interno} · ${v.placa}`));
    cabecera.appendChild(nombreVehiculo);
    cabecera.appendChild(el('span', { className: 'text-xs text-slate-400', text: info.texto }));
    tarjeta.appendChild(cabecera);

    tarjeta.appendChild(el('p', { className: 'text-slate-500 text-xs mt-1', text: v.conductor_nombre ? `Conductor: ${v.conductor_nombre}` : 'Sin conductor asignado' }));

    const acciones = el('div', { className: 'mt-2.5 flex flex-wrap items-center gap-2' });

    if (v.turno_id) {
      const btnCerrar = el('button', { className: 'text-xs font-medium bg-muted hover:bg-secondary text-slate-200 px-2.5 py-1.5 rounded-lg', text: 'Cerrar turno' });
      btnCerrar.addEventListener('click', async () => {
        btnCerrar.disabled = true;
        try {
          await api('/modules/panel/api/turno_cerrar.php', { method: 'POST', body: JSON.stringify({ turno_id: v.turno_id }) });
          await refrescar({ forzar: true });
        } catch (e) {
          alert(e.message);
          btnCerrar.disabled = false;
        }
      });
      acciones.appendChild(btnCerrar);
    } else {
      const select = el('select', { className: 'bg-muted border border-border text-foreground text-xs rounded-lg px-2 py-1.5' });
      select.appendChild(el('option', { text: 'Conductor…', attrs: { value: '' } }));
      for (const c of conductoresCache) {
        select.appendChild(el('option', { text: c.nombre, attrs: { value: c.id } }));
      }
      const btnAbrir = el('button', { className: 'text-xs font-medium bg-accent hover:bg-accent-hover text-on-accent px-2.5 py-1.5 rounded-lg', text: 'Abrir turno' });
      btnAbrir.addEventListener('click', async () => {
        if (!select.value) return;
        btnAbrir.disabled = true;
        try {
          await api('/modules/panel/api/turno_abrir.php', { method: 'POST', body: JSON.stringify({ conductor_id: Number(select.value), vehiculo_id: v.id }) });
          await refrescar({ forzar: true });
        } catch (e) {
          alert(e.message);
          btnAbrir.disabled = false;
        }
      });
      acciones.appendChild(select);
      acciones.appendChild(btnAbrir);
    }

    const selectEstado = el('select', { className: 'bg-muted border border-border text-foreground text-xs rounded-lg px-2 py-1.5' });
    for (const clave of Object.keys(ESTADOS_VEHICULO)) {
      const opt = el('option', { text: ESTADOS_VEHICULO[clave].texto, attrs: { value: clave } });
      if (clave === v.estado_vehiculo) opt.selected = true;
      selectEstado.appendChild(opt);
    }
    selectEstado.addEventListener('change', async () => {
      try {
        await api('/modules/panel/api/vehiculo_estado.php', { method: 'POST', body: JSON.stringify({ vehiculo_id: v.id, estado: selectEstado.value }) });
        await refrescar({ forzar: true });
      } catch (e) {
        alert(e.message);
      }
    });
    acciones.appendChild(selectEstado);

    tarjeta.appendChild(acciones);
    return tarjeta;
  }

  function usuarioEstaEditando() {
    const activo = document.activeElement;
    if (!activo || !['INPUT', 'SELECT', 'TEXTAREA'].includes(activo.tagName)) return false;
    const cola = document.getElementById('cola');
    const flota = document.getElementById('flota');
    const conversaciones = document.getElementById('conversaciones');
    return (cola && cola.contains(activo)) || (flota && flota.contains(activo)) || (conversaciones && conversaciones.contains(activo));
  }

  function tarjetaConversacion(conv) {
    const tarjeta = el('div', { className: 'rounded-xl bg-card border border-border p-3' });

    const cabecera = el('div', { className: 'flex justify-between items-start' });
    cabecera.appendChild(el('span', { className: 'text-foreground font-medium text-sm', text: conv.nombre_contacto || conv.telefono }));
    cabecera.appendChild(insigniaEstado('#F59E0B', conv.estado === 'IA_PAUSADA' ? 'Pausada' : 'Con humano', 'text-xs text-slate-500'));
    tarjeta.appendChild(cabecera);

    if (conv.ultimo_mensaje) {
      tarjeta.appendChild(el('p', { className: 'text-slate-400 text-xs mt-1 line-clamp-2', text: conv.ultimo_mensaje }));
    }
    const atendidaPorOtro = conv.atendida_por !== null && Number(conv.atendida_por) !== usuarioIdActual && usuarioRolActual !== 'ADMIN';

    if (conv.atendida_por_nombre) {
      tarjeta.appendChild(el('p', { className: 'text-slate-500 text-xs mt-1', text: `Atendida por: ${conv.atendida_por_nombre}` }));
    }
    if (atendidaPorOtro) {
      tarjeta.appendChild(el('p', { className: 'text-amber-400 text-xs mt-1', text: 'La está atendiendo otro operador — no podés responder ni liberarla.' }));
    }

    const caja = el('textarea', { className: 'w-full mt-2 bg-muted border border-border text-foreground placeholder:text-slate-500 text-xs rounded-lg px-2.5 py-1.5 disabled:opacity-50', attrs: { rows: '2', placeholder: 'Responder por WhatsApp…' } });
    const acciones = el('div', { className: 'mt-2 flex gap-2' });

    const btnEnviar = el('button', { className: 'text-xs font-medium bg-accent hover:bg-accent-hover text-on-accent px-2.5 py-1.5 rounded-lg', text: 'Enviar' });
    btnEnviar.addEventListener('click', async () => {
      if (!caja.value.trim()) return;
      btnEnviar.disabled = true;
      try {
        await api('/modules/panel/api/conversacion_responder.php', { method: 'POST', body: JSON.stringify({ conversacion_id: conv.id, texto: caja.value.trim() }) });
        await refrescar({ forzar: true });
      } catch (e) {
        alert(e.message);
        btnEnviar.disabled = false;
      }
    });

    const btnLiberar = el('button', { className: 'text-xs font-medium bg-muted hover:bg-secondary text-slate-200 px-2.5 py-1.5 rounded-lg', text: 'Devolver a la IA' });
    btnLiberar.addEventListener('click', async () => {
      btnLiberar.disabled = true;
      try {
        await api('/modules/panel/api/conversacion_liberar.php', { method: 'POST', body: JSON.stringify({ conversacion_id: conv.id }) });
        await refrescar({ forzar: true });
      } catch (e) {
        alert(e.message);
        btnLiberar.disabled = false;
      }
    });

    if (atendidaPorOtro) {
      caja.disabled = true;
      btnEnviar.disabled = true;
      btnLiberar.disabled = true;
    }

    acciones.appendChild(btnEnviar);
    acciones.appendChild(btnLiberar);
    tarjeta.appendChild(caja);
    tarjeta.appendChild(acciones);
    return tarjeta;
  }

  async function refrescar({ forzar = false } = {}) {
    // El polling automático no debe borrarle al operador un motivo a medio
    // escribir o una selección de vehículo/conductor a medio hacer.
    if (!forzar && usuarioEstaEditando()) return;

    const [datosCola, datosFlota, datosConductores, datosConversaciones] = await Promise.all([
      api('/modules/panel/api/cola.php'),
      api('/modules/panel/api/flota.php'),
      api('/modules/panel/api/conductores.php'),
      api('/modules/panel/api/conversaciones.php'),
    ]);

    conductoresCache = datosConductores.conductores;
    vehiculosDisponiblesCache = datosFlota.flota.filter((v) => v.estado_vehiculo === 'DISPONIBLE');

    if (window.SolicitudAlertas) window.SolicitudAlertas.procesar(datosCola.cola);

    const contenedorCola = document.getElementById('cola');
    contenedorCola.replaceChildren();
    if (datosCola.cola.length === 0) {
      contenedorCola.appendChild(el('p', { className: 'text-neutral-700 text-sm', text: 'No hay solicitudes en cola.' }));
    } else {
      for (const carrera of datosCola.cola) {
        contenedorCola.appendChild(tarjetaCarrera(carrera));
      }
    }

    const contenedorFinalizadas = document.getElementById('finalizadas');
    contenedorFinalizadas.replaceChildren();
    for (const carrera of datosCola.finalizadas) {
      contenedorFinalizadas.appendChild(filaFinalizada(carrera));
    }

    const contenedorFlota = document.getElementById('flota');
    contenedorFlota.replaceChildren();
    for (const v of datosFlota.flota) {
      contenedorFlota.appendChild(tarjetaVehiculo(v));
    }

    const contenedorConversaciones = document.getElementById('conversaciones');
    contenedorConversaciones.replaceChildren();
    if (datosConversaciones.conversaciones.length === 0) {
      contenedorConversaciones.appendChild(el('p', { className: 'text-neutral-700 text-sm', text: 'Ninguna por ahora.' }));
    } else {
      for (const conv of datosConversaciones.conversaciones) {
        contenedorConversaciones.appendChild(tarjetaConversacion(conv));
      }
    }
  }

  function inicializarModalSolicitud() {
    const modal = document.getElementById('modal-solicitud');
    const form = document.getElementById('form-solicitud');
    const error = document.getElementById('error-solicitud');

    document.getElementById('btn-nueva-solicitud').addEventListener('click', () => {
      form.reset();
      error.classList.add('hidden');
      modal.showModal();
    });

    document.getElementById('btn-cancelar-solicitud').addEventListener('click', () => modal.close());

    form.addEventListener('submit', async (evento) => {
      evento.preventDefault();
      const datos = Object.fromEntries(new FormData(form).entries());
      error.classList.add('hidden');

      try {
        await api('/modules/panel/api/solicitud_nueva.php', { method: 'POST', body: JSON.stringify(datos) });
        modal.close();
        await refrescar({ forzar: true });
      } catch (e) {
        error.textContent = e.message;
        error.classList.remove('hidden');
      }
    });
  }

  inicializarModalSolicitud();
  refrescar();
  setInterval(refrescar, POLL_MS);
})();
