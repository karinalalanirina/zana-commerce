export default function init() {
    const monthlyBtn = document.getElementById('bill-monthly');
      const yearlyBtn  = document.getElementById('bill-yearly');
      function setBilling(period) {
        document.querySelectorAll('.price').forEach(p => { p.textContent = p.dataset[period] || p.textContent; });
        monthlyBtn.classList.toggle('bg-wa-deep', period==='monthly');
        monthlyBtn.classList.toggle('text-paper-0', period==='monthly');
        monthlyBtn.classList.toggle('text-ink-600', period!=='monthly');
        yearlyBtn.classList.toggle('bg-wa-deep', period==='yearly');
        yearlyBtn.classList.toggle('text-paper-0', period==='yearly');
        yearlyBtn.classList.toggle('text-ink-600', period!=='yearly');
      }
      monthlyBtn.addEventListener('click', () => setBilling('monthly'));
      yearlyBtn.addEventListener('click',  () => setBilling('yearly'));
}
