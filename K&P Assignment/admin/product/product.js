setTimeout(function() {
    const alert = document.getElementById('errorAlert');
    if (alert) {
        alert.classList.add('translate-x-full');
        setTimeout(() => alert.remove(), 500);
    }
}, 5000);


setTimeout(function() {
    const alert = document.getElementById('successAlert');
    if (alert) {
        alert.classList.add('translate-x-full');
        setTimeout(() => alert.remove(), 500);
    }
}, 5000);

// Add script to position alerts dynamically when page loads
document.addEventListener('DOMContentLoaded', function() {
    const header = document.querySelector('.navbar');
    const headerHeight = header ? header.offsetHeight + 10 : 85;

    const errorAlert = document.getElementById('errorAlert');
    if (errorAlert) errorAlert.style.top = `${headerHeight}px`;

    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        successAlert.style.top = errorAlert ?
            `${headerHeight + errorAlert.offsetHeight + 10}px` :
            `${headerHeight}px`;
    }
});