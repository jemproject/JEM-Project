window.onload = function() {
    var iframe = document.querySelector('iframe[src^="https://www.openstreetmap.org/export/embed.html"]');
    if (iframe) {
        iframe.src = iframe.src; // Reload iframe-Source
    }
}