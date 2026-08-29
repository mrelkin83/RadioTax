(() => {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const POLL_MS = 4000;

  const ESTADOS_VEHICULO = {
    DISPONIBLE: { emoji: '🟢', texto: 'Disponible' },
    EN_TURNO: { emoji: '🟢', texto: 'En turno' },
    SOLICITADO: { emoji: '🟡', texto: 'Solicitado' },
    PENDIENTE_CONFIRMACION: { emoji: '🟡', texto: 'Pendiente confirmación' },
    EN_SERVICIO: { emoji: '🔵', texto: 'En servicio' },
    FUERA_DE_TURNO: { emoji: '⚪', texto: 'Fuera de turno' },
    NO_DISPONIBLE: { emoji: '🔴', texto: 'No disponible' },
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
    const tarjeta = el('div', { className: 'rounded-lg bg-slate-800 p-4', attrs: { 'data-carrera-id': carrera.id } });

    const cabecera = el('div', { className: 'flex justify-between text-xs text-slate-400 mb-1' });
    cabecera.appendChild(el('span', { text: `#${carrera.id} · ${carrera.estado}` }));
    cabecera.appendChild(el('span', { text: tiempoDesde(carrera.creado_en) }));
    tarjeta.appendChild(cabecera);

    tarjeta.appendChild(el('p', { className: 'text-white font-medium', text: carrera.cliente_nombre || carrera.cliente_whatsapp }));
    tarjeta.appendChild(el('p', { className: 'text-slate-300 text-sm', text: `📍 ${carrera.recogida_texto}` }));
    tarjeta.appendChild(el('p', { className: 'text-slate-300 text-sm', text: `🎯 ${carrera.destino_texto}` }));

    if (carrera.observaciones) {
      tarjeta.appendChild(el('p', { className: 'text-slate-500 text-xs mt-1', text: carrera.observaciones }));
    }

    const acciones = el('div', { className: 'mt-3 flex flex-wrap items-center gap-2' });

    const asignable = !['ASIGNADA', 'ACEPTADA', 'EN_CAMINO', 'EN_SERVICIO'].includes(carrera.estado);
    if (asignable) {
      const select = el('select', { className: 'bg-slate-700 text-white text-sm rounded px-2 py-1' });
      select.appendChild(el('option', { text: 'Vehículo…', attrs: { value: '' } }));
      for (const v of vehiculosDisponiblesCache) {
        select.appendChild(el('option', { text: `${v.numero_interno} · ${v.placa}`, attrs: { value: v.id } }));
      }
      const btnAsignar = el('button', { className: 'text-sm bg-sky-600 hover:bg-sky-500 text-white px-2 py-1 rounded', text: 'Asignar' });
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
      const btnAvanzar = el('button', { className: 'text-sm bg-emerald-700 hover:bg-emerald-600 text-white px-2 py-1 rounded', text: etiquetasTransicion[destino] });
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

    // Una carrera con el cliente ya a bordo (EN_SERVICIO) no se cancela: de
    // ahí solo se sale finalizándola (regla del ciclo, §6 del system prompt maestro).
    if (carrera.estado !== 'EN_SERVICIO') {
      const inputMotivo = el('input', { className: 'bg-slate-700 text-white text-sm rounded px-2 py-1 w-32', attrs: { placeholder: 'Motivo' } });
      const btnCancelar = el('button', { className: 'text-sm bg-red-700 hover:bg-red-600 text-white px-2 py-1 rounded', text: 'Cancelar' });
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
    const fila = el('div', { className: 'flex justify-between text-sm bg-slate-900 rounded px-3 py-2' });
    fila.appendChild(el('span', { text: `#${carrera.id} · ${carrera.cliente_nombre || carrera.cliente_whatsapp}` }));
    fila.appendChild(el('span', { className: 'text-slate-400', text: carrera.estado }));
    return fila;
  }

  function tarjetaVehiculo(v) {
    const info = ESTADOS_VEHICULO[v.estado_vehiculo] || { emoji: '❔', texto: v.estado_vehiculo };
    const tarjeta = el('div', { className: 'rounded-lg bg-slate-800 p-3' });

    const cabecera = el('div', { className: 'flex justify-between items-center' });
    cabecera.appendChild(el('span', { className: 'text-white font-medium', text: `${info.emoji} ${v.numero_interno} · ${v.placa}` }));
    cabecera.appendChild(el('span', { className: 'text-xs text-slate-400', text: info.texto }));
    tarjeta.appendChild(cabecera);

    tarjeta.appendChild(el('p', { className: 'text-slate-400 text-xs mt-1', text: v.conductor_nombre ? `Conductor: ${v.conductor_nombre}` : 'Sin conductor asignado' }));

    const acciones = el('div', { className: 'mt-2 flex flex-wrap items-center gap-2' });

    if (v.turno_id) {
      const btnCerrar = el('button', { className: 'text-xs bg-slate-700 hover:bg-slate-600 text-white px-2 py-1 rounded', text: 'Cerrar turno' });
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
      const select = el('select', { className: 'bg-slate-700 text-white text-xs rounded px-2 py-1' });
      select.appendChild(el('option', { text: 'Conductor…', attrs: { value: '' } }));
      for (const c of conductoresCache) {
        select.appendChild(el('option', { text: c.nombre, attrs: { value: c.id } }));
      }
      const btnAbrir = el('button', { className: 'text-xs bg-emerald-700 hover:bg-emerald-600 text-white px-2 py-1 rounded', text: 'Abrir turno' });
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

    const selectEstado = el('select', { className: 'bg-slate-700 text-white text-xs rounded px-2 py-1' });
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
    const tarjeta = el('div', { className: 'rounded-lg bg-slate-800 p-3' });

    const cabecera = el('div', { className: 'flex justify-between items-start' });
    cabecera.appendChild(el('span', { className: 'text-white font-medium text-sm', text: conv.nombre_contacto || conv.telefono }));
    cabecera.appendChild(el('span', { className: 'text-xs text-slate-500', text: conv.estado === 'IA_PAUSADA' ? 'Pausada' : 'Con humano' }));
    tarjeta.appendChild(cabecera);

    if (conv.ultimo_mensaje) {
      tarjeta.appendChild(el('p', { className: 'text-slate-400 text-xs mt-1 line-clamp-2', text: conv.ultimo_mensaje }));
    }
    if (conv.atendida_por_nombre) {
      tarjeta.appendChild(el('p', { className: 'text-slate-500 text-xs mt-1', text: `Atendida por: ${conv.atendida_por_nombre}` }));
    }

    const caja = el('textarea', { className: 'w-full mt-2 bg-slate-700 text-white text-xs rounded px-2 py-1', attrs: { rows: '2', placeholder: 'Responder por WhatsApp…' } });
    const acciones = el('div', { className: 'mt-2 flex gap-2' });

    const btnEnviar = el('button', { className: 'text-xs bg-sky-600 hover:bg-sky-500 text-white px-2 py-1 rounded', text: 'Enviar' });
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

    const btnLiberar = el('button', { className: 'text-xs bg-slate-700 hover:bg-slate-600 text-white px-2 py-1 rounded', text: 'Devolver a la IA' });
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

    const contenedorCola = document.getElementById('cola');
    contenedorCola.replaceChildren();
    if (datosCola.cola.length === 0) {
      contenedorCola.appendChild(el('p', { className: 'text-slate-500 text-sm', text: 'No hay solicitudes en cola.' }));
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
      contenedorConversaciones.appendChild(el('p', { className: 'text-slate-500 text-sm', text: 'Ninguna por ahora.' }));
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
