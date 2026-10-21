/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./index.php", "./panel/**/*.php", "./assets/js/**/*.js"],
  safelist: [
    // Clases generadas dinámicamente en JS (assets/js/app.js, simulación de tracking)
    { pattern: /^bg-(amber|emerald|orange|sky)-(50|500)$/ },
    { pattern: /^border-(amber|emerald|orange|sky)-200$/ },
    { pattern: /^text-(amber|emerald|orange|sky)-700$/ },
  ],
  theme: {
    extend: {
      colors: {
        rubi: {
          navy: '#082d42',
          navy2: '#0b3954',
          emerald: '#065f46',
          emerald2: '#047857',
          orange: '#f97316',
        },
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
