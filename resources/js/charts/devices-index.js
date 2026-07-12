export default function init() {
    document.querySelectorAll('#status-tabs .status-tab').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('#status-tabs .status-tab').forEach(x => {
          x.classList.remove('bg-wa-deep','text-paper-0');
          x.classList.add('text-ink-600','hover:bg-paper-100');
        });
        b.classList.add('bg-wa-deep','text-paper-0');
        b.classList.remove('text-ink-600','hover:bg-paper-100');
      }));

      document.getElementById('add-device-btn').addEventListener('click', () => {
        document.getElementById('add-modal').classList.remove('hidden');
        document.getElementById('add-modal').classList.add('flex');
      });
      function closeAddModal() {
        document.getElementById('add-modal').classList.add('hidden');
        document.getElementById('add-modal').classList.remove('flex');
      }
      window.closeAddModal = closeAddModal;
}
