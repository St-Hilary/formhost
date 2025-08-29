// Proxy handler for CORS issues with mp.sthilary.org
(function() {
  // Store the original fetch function
  const originalFetch = window.fetch;

  // Override the fetch function
  window.fetch = function(url, options) {
    // Check if the URL is for mp.sthilary.org
    if (typeof url === 'string' && url.includes('mp.sthilary.org')) {
      // Redirect through our proxy
      const proxyUrl = `/proxy.php?url=${encodeURIComponent(url)}`;
      return originalFetch(proxyUrl, options);
    }
    
    // For all other URLs, use the original fetch
    return originalFetch(url, options);
  };

  // Also handle XMLHttpRequest for older code
  const originalXHROpen = XMLHttpRequest.prototype.open;
  XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
    // Check if the URL is for mp.sthilary.org
    if (typeof url === 'string' && url.includes('mp.sthilary.org')) {
      // Redirect through our proxy
      const proxyUrl = `/proxy.php?url=${encodeURIComponent(url)}`;
      return originalXHROpen.call(this, method, proxyUrl, async, user, password);
    }
    
    // For all other URLs, use the original open
    return originalXHROpen.call(this, method, url, async, user, password);
  };

  console.log('CORS proxy handler initialized for mp.sthilary.org');
})();