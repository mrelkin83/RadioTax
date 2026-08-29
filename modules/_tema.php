<?php
/**
 * Sistema de diseño compartido — Centro de Transmisión / Administración / Plataforma.
 * Un solo lugar para la paleta, tipografía y estados base; cada página lo incluye
 * en <head> en vez de cargar Tailwind suelto. "Dark tech + status green": pensado
 * para una sala de control (alto contraste, escaneo rápido, sin ruido visual).
 */
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600;700&family=Fira+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: {
          sans: ['"Fira Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
          mono: ['"Fira Code"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
        },
        colors: {
          background: '#0F172A',
          foreground: '#F8FAFC',
          card: '#1B2336',
          'card-foreground': '#F8FAFC',
          primary: '#1E293B',
          'on-primary': '#FFFFFF',
          secondary: '#334155',
          'on-secondary': '#FFFFFF',
          accent: '#22C55E',
          'accent-hover': '#16A34A',
          'on-accent': '#052e16',
          muted: '#232B41',
          'muted-foreground': '#94A3B8',
          border: '#334155',
          destructive: '#EF4444',
          'destructive-hover': '#DC2626',
          warning: '#F59E0B',
          ring: '#22C55E',
        },
      },
    },
  };
</script>
<style>
  body { font-family: 'Fira Sans', ui-sans-serif, system-ui, sans-serif; background: #0F172A; color: #F8FAFC; position: relative; isolation: isolate; }
  .font-mono, .num { font-family: 'Fira Code', ui-monospace, monospace; font-variant-numeric: tabular-nums; }
  ::selection { background: #22C55E; color: #052e16; }
  /* Foco visible solo por teclado (§ux focus-visible), nunca al hacer click con el mouse. */
  :focus { outline: none; }
  :focus-visible { outline: 2px solid #22C55E; outline-offset: 2px; border-radius: 4px; box-shadow: 0 0 0 4px rgba(34, 197, 94, .18); }
  input, select, textarea, button { transition: background-color 150ms ease, border-color 150ms ease, opacity 150ms ease, transform 150ms ease; }
  button:not(:disabled), a[href], select, [role="button"] { cursor: pointer; }
  button:disabled { cursor: not-allowed; opacity: .5; }
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { transition-duration: 0.01ms !important; animation-duration: 0.01ms !important; }
  }
  /* Barra de estado (punto), no emoji — un indicador real de UI, no un icono decorativo. */
  .punto-estado { display: inline-block; width: .55rem; height: .55rem; border-radius: 9999px; flex: none; }

  /* Profundidad ambiental: dos manchas de luz fijas y muy suaves detrás del contenido. */
  body::before, body::after {
    content: '';
    position: fixed;
    border-radius: 9999px;
    z-index: -1;
    pointer-events: none;
    filter: blur(90px);
  }
  body::before { width: 44rem; height: 44rem; top: -14rem; right: -12rem; background: radial-gradient(closest-side, rgba(34, 197, 94, .16), transparent); }
  body::after { width: 38rem; height: 38rem; bottom: -16rem; left: -10rem; background: radial-gradient(closest-side, rgba(56, 189, 248, .10), transparent); }

  /* Vidrio: las tarjetas flotan sobre el fondo, con borde de luz y sombra real en vez de un plano opaco. */
  .bg-card {
    background-color: rgba(27, 35, 54, .70);
    backdrop-filter: blur(20px) saturate(150%);
    -webkit-backdrop-filter: blur(20px) saturate(150%);
    box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, .06), 0 12px 32px -16px rgba(0, 0, 0, .6);
    transition: box-shadow 200ms ease;
  }
  .bg-card:hover { box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, .09), 0 18px 44px -16px rgba(0, 0, 0, .7); }
  .border-border { border-color: rgba(255, 255, 255, .12); }
  .backdrop-blur { backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }

  /* Botón principal: relieve sutil + resplandor del color de acento, no un plano de color liso. */
  .bg-accent {
    background-image: linear-gradient(180deg, rgba(255, 255, 255, .16), rgba(255, 255, 255, 0) 55%), linear-gradient(180deg, #22C55E, #16A34A);
    box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, .25), 0 8px 20px -8px rgba(34, 197, 94, .5);
  }
  .bg-accent:hover { filter: brightness(1.05); box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, .25), 0 10px 26px -6px rgba(34, 197, 94, .65); }
  .bg-accent:active { filter: brightness(.96); }

  /* Scrollbar a tono con el sistema, en vez del gris genérico del navegador. */
  * { scrollbar-width: thin; scrollbar-color: #334155 transparent; }
  ::-webkit-scrollbar { width: 10px; height: 10px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: #334155; border-radius: 9999px; border: 2px solid transparent; background-clip: padding-box; }
  ::-webkit-scrollbar-thumb:hover { background: #475569; background-clip: padding-box; }

  /* Entrada suave del contenido principal (respeta prefers-reduced-motion arriba). */
  @keyframes entrada-suave { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
  main { animation: entrada-suave 420ms ease-out; }
</style>
