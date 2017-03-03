/* global wpApiSettings: false */


jQuery(document).ready(function($){
    'use strict';

    // Validate
    $(document).on('click', '.sekisyo-btn', function(){
        var $btn = $(this);
        var $row = $btn.parents('.sekisyo-row');
        var license = $btn.prev('input').val();
        $row.addClass('loading');
        $.ajax( {
            url: wpApiSettings.root + 'sekisyo/v1/license/' + $row.attr('data-sekisyo-guid') + '/',
            method: 'POST',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', wpApiSettings.nonce );
            },
            data:{
                license: license
            }
        }).done(function(response){
            $row.parent().html( response.html );
            window.alert( response.message );

        }).fail(function(res){
            $row.removeClass('loading');
            window.alert( res.responseJSON.message );
        });
    });

    // Unlink
    $(document).on('click', '.sekisyo-unlink', function(){
        var $btn = $(this);
        var $row = $btn.parents('.sekisyo-row');
        var license = $row.find('input[name="sekisyo-license"]').val();
        $row.addClass('loading');
        $.ajax( {
            url: wpApiSettings.root + 'sekisyo/v1/license/' + $row.attr('data-sekisyo-guid') + '?license=' + license,
            method: 'DELETE',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', wpApiSettings.nonce );
            }
        }).done(function(response){
            $row.parent().html( response.html );
            window.alert( response.message );

        }).fail(function(res){
            $row.removeClass('loading');
            window.alert( res.responseJSON.message );
        });
    });

});
