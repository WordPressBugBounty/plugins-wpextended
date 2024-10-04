window.addEventListener('DOMContentLoaded', (event) => {
  if( typeof wpext_download_url === "undefined" || !wpext_download_url ) {
    return;
  }

  const filename = wpext_download_url.match( /filename=([^&]+)/ )[1];

  let current_url = window.location.href;
  current_url = current_url.replace( 'wpext-export=' + encodeURIComponent(wpext_download_url), '' );

  fetch( wpext_download_url )
  .then(resp => resp.blob())
  .then(blob => {
    const url = window.URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    
    // the filename you want
    a.download = filename;

    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    
    window.history.replaceState( {}, "", current_url );
  })
  .catch(() => {
    console.log('failed to download');

    // try via window
    const download = window.URL( wpext_download_url );

    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    
    // the filename you want
    a.download = filename;

    document.body.appendChild(a);
    a.click();

    window.history.replaceState( {}, "", current_url );
  });
});