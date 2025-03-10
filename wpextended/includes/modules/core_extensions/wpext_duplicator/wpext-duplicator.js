window.addEventListener('DOMContentLoaded', function(){
  document.body.addEventListener('click', ( event ) => {
    const btn = event.target;

    if( typeof btn.dataset.duplicate == 'undefined' ) {
      return;
    }
    event.preventDefault();

    fetch( btn.href )
    .then( response => {
      if( !response.ok ) {
        response.text().then((error) => {
          alert("Error: " + error);
        });
        return;
      }

      response.json()
      .then( result => {
        if( !result.status ) {
          alert( result.error || "Something went wrong" );
          return;
        }

        if( !result.duplicate || !result.duplicate.edit_url ) {
          alert( "Something went wrong" );
          location.reload();
          return;
        }
        
        location.assign( result.duplicate.edit_url );
      })
      .catch( error => {
        alert( error );
      })
    })
    .catch( error => {
      alert( error );
    })
  });
});
