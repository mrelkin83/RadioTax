<?php
/**
 * Sistema de diseño compartido — Centro de Transmisión / Administración / Plataforma.
 * Un solo lugar para la paleta, tipografía y estados base; cada página lo incluye
 * en <head> en vez de cargar Tailwind suelto. "Taxi negro y amarillo": los colores
 * clásicos del oficio, no un dashboard genérico — negro real (no azul oscuro) con
 * el amarillo como acento, alto contraste, escaneo rápido, sin ruido visual.
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
          background: '#0B0B0C',
          foreground: '#FAFAFA',
          card: '#161616',
          'card-foreground': '#FAFAFA',
          primary: '#1A1A1A',
          'on-primary': '#FFFFFF',
          secondary: '#2A2A2A',
          'on-secondary': '#FFFFFF',
          accent: '#FACC15',
          'accent-hover': '#EAB308',
          'on-accent': '#141200',
          muted: '#1C1C1C',
          'muted-foreground': '#A3A3A3',
          border: '#3A3A3A',
          destructive: '#EF4444',
          'destructive-hover': '#DC2626',
          warning: '#F97316',
          ring: '#FACC15',
        },
      },
    },
  };
</script>
<style>
  body { font-family: 'Fira Sans', ui-sans-serif, system-ui, sans-serif; background: #0B0B0C; color: #FAFAFA; position: relative; isolation: isolate; }
  .font-mono, .num { font-family: 'Fira Code', ui-monospace, monospace; font-variant-numeric: tabular-nums; }
  ::selection { background: #FACC15; color: #141200; }
  /* Foco visible solo por teclado (§ux focus-visible), nunca al hacer click con el mouse. */
  :focus { outline: none; }
  :focus-visible { outline: 2px solid #FACC15; outline-offset: 2px; border-radius: 4px; box-shadow: 0 0 0 4px rgba(250, 204, 21, .22); }
  input, select, textarea, button { transition: background-color 150ms ease, border-color 150ms ease, opacity 150ms ease, transform 150ms ease; }
  button:not(:disabled), a[href], select, [role="button"] { cursor: pointer; }
  button:disabled { cursor: not-allowed; opacity: .5; }
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { transition-duration: 0.01ms !important; animation-duration: 0.01ms !important; }
  }
  /* Barra de estado (punto), no emoji — un indicador real de UI, no un icono decorativo. */
  .punto-estado { display: inline-block; width: .55rem; height: .55rem; border-radius: 9999px; flex: none; }

  /* Profundidad ambiental: dos manchas de luz amarilla, fijas y muy suaves, detrás del contenido. */
  body::before, body::after {
    content: '';
    position: fixed;
    border-radius: 9999px;
    z-index: -1;
    pointer-events: none;
    filter: blur(90px);
  }
  body::before { width: 44rem; height: 44rem; top: -14rem; right: -12rem; background: radial-gradient(closest-side, rgba(250, 204, 21, .14), transparent); }
  body::after { width: 38rem; height: 38rem; bottom: -16rem; left: -10rem; background: radial-gradient(closest-side, rgba(234, 179, 8, .08), transparent); }

  /* Vidrio: las tarjetas flotan sobre el fondo negro, con borde de luz y sombra real en vez de un plano opaco. */
  .bg-card {
    background-color: rgba(22, 22, 22, .72);
    backdrop-filter: blur(20px) saturate(150%);
    -webkit-backdrop-filter: blur(20px) saturate(150%);
    box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, .06), 0 12px 32px -16px rgba(0, 0, 0, .7);
    transition: box-shadow 200ms ease;
  }
  .bg-card:hover { box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, .09), 0 18px 44px -16px rgba(0, 0, 0, .8); }
  .border-border { border-color: rgba(255, 255, 255, .12); }
  .backdrop-blur { backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }

  /* Botón principal: relieve sutil + resplandor del amarillo, no un plano de color liso. */
  .bg-accent {
    background-image: linear-gradient(180deg, rgba(255, 255, 255, .22), rgba(255, 255, 255, 0) 55%), linear-gradient(180deg, #FACC15, #EAB308);
    box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, .3), 0 8px 20px -8px rgba(250, 204, 21, .45);
  }
  .bg-accent:hover { filter: brightness(1.05); box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, .3), 0 10px 26px -6px rgba(250, 204, 21, .6); }
  .bg-accent:active { filter: brightness(.96); }

  /* Scrollbar a tono con el sistema, en vez del gris genérico del navegador. */
  * { scrollbar-width: thin; scrollbar-color: #3A3A3A transparent; }
  ::-webkit-scrollbar { width: 10px; height: 10px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: #3A3A3A; border-radius: 9999px; border: 2px solid transparent; background-clip: padding-box; }
  ::-webkit-scrollbar-thumb:hover { background: #525252; background-clip: padding-box; }

  /* Entrada suave del contenido principal (respeta prefers-reduced-motion arriba). */
  @keyframes entrada-suave { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
  main { animation: entrada-suave 420ms ease-out; }
</style>
