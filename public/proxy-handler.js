// Proxy handler for CORS issues with mpi.ministryplatform.com
(function() {
  // Store the original fetch function
  const originalFetch = window.fetch;

  // Override the fetch function
  window.fetch = function(url, options) {
    // Check if the URL is for mpi.ministryplatform.com
    if (typeof url === 'string' && url.includes('mpi.ministryplatform.com')) {
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
    // Check if the URL is for mpi.ministryplatform.com
    if (typeof url === 'string' && url.includes('mpi.ministryplatform.com')) {
      // Redirect through our proxy
      const proxyUrl = `/proxy.php?url=${encodeURIComponent(url)}`;
      return originalXHROpen.call(this, method, proxyUrl, async, user, password);
    }
    
    // For all other URLs, use the original open
    return originalXHROpen.call(this, method, url, async, user, password);
  };

  console.log('CORS proxy handler initialized for mpi.ministryplatform.com');
})();