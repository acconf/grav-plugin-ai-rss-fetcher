/**
 * AI RSS Fetcher JavaScript
 * Provides functionality for the AI RSS Fetcher plugin
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any JavaScript functionality here
    var rssCards = document.querySelectorAll('.ai-rss-fetcher-card');
    
    if (rssCards.length) {
        // Add click event to cards for easier navigation
        rssCards.forEach(function(card) {
            var link = card.querySelector('a.btn');
            if (link) {
                var linkUrl = link.getAttribute('href');
                
                // Make the entire card clickable, except for the button itself
                card.addEventListener('click', function(e) {
                    // Avoid triggering when clicking the button itself
                    if (!e.target.closest('.btn')) {
                        window.open(linkUrl, '_blank');
                    }
                });
                
                // Add cursor pointer style
                card.style.cursor = 'pointer';
            }
        });
    }
});