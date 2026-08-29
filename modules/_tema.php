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
  body { font-family: 'Fira Sans', ui-sans-serif, system-ui, sans-serif; background: #0F172A; color: #F8FAFC; }
  .font-mono, .num { font-family: 'Fira Code', ui-monospace, monospace; font-variant-numeric: tabular-nums; }
  ::selection { background: #22C55E; color: #052e16; }
  /* Foco visible solo por teclado (§ux focus-visible), nunca al hacer click con el mouse. */
  :focus { outline: none; }
  :focus-visible { outline: 2px solid #22C55E; outline-offset: 2px; border-radius: 4px; }
  input, select, textarea, button { transition: background-color 150ms ease, border-color 150ms ease, opacity 150ms ease, transform 150ms ease; }
  button:not(:disabled), a[href], select, [role="button"] { cursor: pointer; }
  button:disabled { cursor: not-allowed; opacity: .5; }
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { transition-duration: 0.01ms !important; animation-duration: 0.01ms !important; }
  }
  /* Barra de estado (punto), no emoji — un indicador real de UI, no un icono decorativo. */
  .punto-estado { display: inline-block; width: .55rem; height: .55rem; border-radius: 9999px; flex: none; }
</style>
