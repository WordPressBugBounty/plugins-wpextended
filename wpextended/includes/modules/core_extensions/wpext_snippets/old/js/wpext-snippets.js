jQuery(function($){
    
  var block = $('#list-of-snippets'),
      ul    = block.find( '#snippets-list' ),
      form  = block.find( '#wpext-snippets-codes-json' );

  var codesTxt = $('#wpext-snippets-codes-json').val(),
      codes = JSON.parse( codesTxt );

  var tpl = $( block.find('template').html() );

  block
  .on('change', function(event){
    // collect everything into array
    let data = [];
    ul.children().each(function(i,li){
      let $li = $(li);
      data.push({
        position:  $li.find('.snippet-position').val(),
        code: $li.find('.snippet-code').val(),
        label: $li.find('.snippet-label').val(),
      });
    });

    form.val( JSON.stringify(data) );
  })
  .on('click', '.btn-up', function(event){
    event.preventDefault();

    var btn = $( this ),
        li = btn.closest('li'),
        prev = li.prev();
		if(prev.length >= 1){
          li.detach().insertBefore( prev );
          ul.trigger('change');
        }
  })
  .on('click', '.btn-down', function(event){
    event.preventDefault();

    var btn = $( this ),
        li = btn.closest('li'),
        next = li.next();
		if(next.length >= 1){
          li.detach().insertAfter( next );
          ul.trigger('change');  
        }    
  })
  .on('click', '.btn-delete', function(event){
    event.preventDefault();

    const btn = $( this ),
        li = btn.closest('li');
    
    let msg = btn.data('alert');
    
    if( !msg ) {
      msg = 'Are you sure you want to delete?';
    }

    if( !confirm( msg ) ) {
      return;
    }

    li.remove();

    ul.trigger('change');
  })
  .on('click', '#add_new_code', function(event){

    _populateItem( {} );

  });


  function _populateItem( item ){
    let li = tpl.clone();

    // get list of names
    let names = [];
    li.find( 'input,select,texarea' )
    .filter('[name$="[]"]')
    .each(function(i,e){
      if( names.indexOf( e.name ) == -1 )
        names.push( e.name );
    });

    $.each( names, function(i,name){
      let id;
      do {
        id = '_' + Math.random().toString(36).substr(2, 9);
      } while( $('[name="' + name.replace( /\[\]$/, '[' + id + ']' + '"]') ).length );

      li.find('[name="' + name + '"]').attr('name', name.replace( /\[\]$/, '[' + id + ']' ) );
    })

    /*li
    .find('.snippet-position')
    .filter('[value="' + ( item.position || 'head' ) + '"]')
    .prop('checked', true );*/

    li
    .find('.snippet-position')
    .val(item.position || 'head');

    
    if( item.code ) {
      li.find('.snippet-code').val( item.code );
    }

    if( item.label ) {
      li.find('.snippet-label').val( item.label );
    }
    
    ul.append( li );
  }

  $.each( codes, function(i,item){

    _populateItem( item );

  });
  if (ul.children().length <= 1 ) {
    $('.wpext_up_down').hide();
  }

jQuery('#add_new_snippet').click(function(){
  jQuery('#add_new_code').trigger('click');
 });
 jQuery('.wpext_code_snippet .save_snippet').click(function(){
  jQuery('.wpext_code_snippet .block.py-3 .button-primary').trigger('click');
 });

  // Check if no <li> elements are present in the #snippets-list
  if (jQuery('#snippets-list li').length === 0) {
      // Trigger click on #add_new_code
      jQuery('#add_new_code').trigger('click');
  }
 
}); 
