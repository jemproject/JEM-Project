function loadRSSFeed(options) {
    const defaults = {
        containerId: 'feed-container',
        feedUrl: '',
        maxItems: 10,
        showTitle: true,
        dateFormat: 'de-DE',
        titleTag: 'h2',
        itemTitleTag: 'h3'
    };
    
    const settings = {...defaults, ...options};
    const feedContainer = document.getElementById(settings.containerId);
    
    if (!feedContainer) {
        console.error(`Container mit ID '${settings.containerId}' nicht gefunden`);
        return;
    }
    
    // Entferne die Ladeanzeige, wenn vorhanden
    const loadingElem = feedContainer.querySelector('.loading');
    if (loadingElem) {
        feedContainer.removeChild(loadingElem);
    }
    
    fetch(`https://api.rss2json.com/v1/api.json?rss_url=${encodeURIComponent(settings.feedUrl)}`)
        .then(response => response.json())
        .then(data => {
            if (settings.showTitle && data.feed.title) {
                const feedTitle = document.createElement(settings.titleTag);
                feedTitle.textContent = data.feed.title;
                feedContainer.appendChild(feedTitle);
            }
            
            // Nur die gewünschte Anzahl von Items anzeigen
            const items = data.items.slice(0, settings.maxItems);
            
            items.forEach(item => {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'feed-item';
                
                // Titel und Link
                const title = document.createElement(settings.itemTitleTag);
                const link = document.createElement('a');
                link.href = item.link;
                link.textContent = item.title;
                title.appendChild(link);
                itemDiv.appendChild(title);
                
                // Beschreibung parsen
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = item.description;
                
                // Text nach "Title: " extrahieren
                const titleMatch = item.description.match(/Title: ([^<]+)/);
                if (titleMatch && titleMatch[1]) {
                    const titleDiv = document.createElement('div');
                    titleDiv.className = 'Title';
                    titleDiv.textContent = titleMatch[1].trim();
                    itemDiv.appendChild(titleDiv);
                }
                
                // Text nach "Venue: " extrahieren
                const venueMatch = item.description.match(/Venue: ([^<]+)/);
                if (venueMatch && venueMatch[1]) {
                    const venueDiv = document.createElement('div');
                    venueDiv.className = 'Venue';
                    venueDiv.textContent = venueMatch[1].trim();
                    itemDiv.appendChild(venueDiv);
                }
                
                // Text nach "Category: " extrahieren
                const categoryMatch = item.description.match(/Category: ([^<]+)/);
                if (categoryMatch && categoryMatch[1]) {
                    const categoryDiv = document.createElement('div');
                    categoryDiv.className = 'Category';
                    categoryDiv.textContent = categoryMatch[1].trim();
                    itemDiv.appendChild(categoryDiv);
                }
                
                // Datum und Zeit extrahieren und beibehalten
                const dateMatch = item.description.match(/Date: (.*?)(?:<br>Description:|$)/s);
                if (dateMatch && dateMatch[1]) {
                    const dateDiv = document.createElement('div');
                    dateDiv.className = 'Date';
                    dateDiv.innerHTML = dateMatch[1].trim();
                    itemDiv.appendChild(dateDiv);
                }
                
                feedContainer.appendChild(itemDiv);
            });
        })
        .catch(error => {
            feedContainer.innerHTML = `<p>Fehler beim Laden des Feeds: ${error.message}</p>`;
        });
}