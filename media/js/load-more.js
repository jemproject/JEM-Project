/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
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
    
    // Array for already displayed months
    let displayedMonths = [];
    
    // Initialize displayed months by collecting already visible month rows on page load
    function initDisplayedMonths() {
        $('.eventlist .row-month').each(function() {
            const monthText = $(this).text().trim();
            if (monthText && !displayedMonths.includes(monthText)) {
                displayedMonths.push(monthText);
            }
        });
    }
    
    // Collect already visible months on page start
    initDisplayedMonths();

    // Function for animated fade-in of events
    function animateEvents($newEvents, delay = 20) {
        $newEvents.each(function(index) {
            const $event = $(this);
            // Initially hide events
            $event.css({
                'opacity': '0',
                'transform': 'translateY(-40px)',
                'transition': 'opacity 0.3s ease, transform 0.3s ease'
            });
            
            // Fade in with delay
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

            // Maintain current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('format', 'json');
            urlParams.set('offset', currentOffset + limit);
            urlParams.set('limit', limit);
            
            // Add already displayed months as parameters
            displayedMonths.forEach(function(month, index) {
                urlParams.append('displayedMonths[' + index + ']', month);
            });

            $.ajax({
                url: window.location.pathname + '?' + urlParams.toString(),
                type: 'GET',
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.html && response.html.trim()) {
                        // Convert HTML to jQuery object
                        const $newEvents = $(response.html);
                        
                        // Add new events to list (initially hidden)
                        $eventList.append($newEvents);
                        
                        // Animate events fade-in
                        animateEvents($newEvents);
                        
                        currentOffset += limit;
                        $loadMoreBtn.data('offset', currentOffset);
                        
                        // Update displayedMonths from server response
                        if (response.displayedMonths) {
                            displayedMonths = response.displayedMonths;
                        }
                        
                        // Hide button if no more events available
                        if (!response.hasMore) {
                            // Hide button only after animation completes
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