document.addEventListener('DOMContentLoaded', () => {
    const qrApp = document.getElementById('qrApp');
    const pdfBaseUrl = qrApp.dataset.pdfBaseUrl;

    const rangeInput = document.getElementById('sizeRange');
    const pdfLink = document.getElementById('pdfLink');
    const qrContainer = document.getElementById('qrContainer');

    rangeInput.addEventListener('input', () => {
        const size = rangeInput.value;

        // Adapter taille visuelle du QR
        qrContainer.style.width = size + 'px';
        qrContainer.style.height = size + 'px';

        // Met à jour l'URL dynamiquement
        const newUrl = pdfBaseUrl.replace(/\/0$/, '/' + size);
        pdfLink.href = newUrl;
    });
});
