/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

jQuery(document).ready(function($) {
    const $loadMoreBtn = $('#jem-load-more-btn');
    const $eventList = $('.eventlist');
    let currentOffset = parseInt($loadMoreBtn.data('offset') || 0);
    const limit = parseInt($loadMoreBtn.data('limit') || 10);
    const textLoading = $loadMoreBtn.data('text-loading') || 'Loading...';
    const textLoadMore = $loadMoreBtn.data('text-loadmore') || 'Load More';
    let isLoading = false;

    // Funktion für das animierte Einblenden der Events
    function animateEvents($newEvents, delay = 20) {
        $newEvents.each(function(index) {
            const $event = $(this);
            // Events initial verstecken
            $event.css({
                'opacity': '0',
                'transform': 'translateY(-40px)',
                'transition': 'opacity 0.3s ease, transform 0.3s ease'
            });
            
            // Mit Verzögerung einblenden
            setTimeout(function() {
                $event.css({
                    'opacity': '1',
                    'transform': 'translateY(0)'
                });
            }, index * delay);
        });
    }

    if ($loadMoreBtn.length) {
        $loadMoreBtn.on('click', function(e) {
            e.preventDefault();
            
            if (isLoading) return;
            
            isLoading = true;
            $loadMoreBtn.text(textLoading).prop('disabled', true);

            // Aktuelle URL-Parameter beibehalten
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('format', 'json');
            urlParams.set('offset', currentOffset + limit);
            urlParams.set('limit', limit);

            $.ajax({
                url: window.location.pathname + '?' + urlParams.toString(),
                type: 'GET',
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.html && response.html.trim()) {
                        // HTML in jQuery-Objekt umwandeln
                        const $newEvents = $(response.html);
                        
                        // Neue Events zur Liste hinzufügen (initial versteckt)
                        $eventList.append($newEvents);
                        
                        // Events animiert einblenden
                        animateEvents($newEvents);
                        
                        currentOffset += limit;
                        $loadMoreBtn.data('offset', currentOffset);
                        
                        // Button verstecken wenn keine weiteren Events vorhanden
                        if (!response.hasMore) {
                            // Button erst nach der Animation verstecken
                            setTimeout(function() {
                                $loadMoreBtn.hide();
                            }, $newEvents.length * 150 + 200);
                        }
                    } else {
                        $loadMoreBtn.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading more events:', error);
                    $loadMoreBtn.hide();
                },
                complete: function() {
                    isLoading = false;
                    $loadMoreBtn.text(textLoadMore).prop('disabled', false);
                }
            });
        });
    }
});