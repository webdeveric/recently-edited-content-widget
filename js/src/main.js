(function( $, window ) {
    "use strict";

    $( function() {

        var form   = $("#recw-search"),
            output = $("#recw-search-output"),
            search = $("#recw-search-input"),
            cache = {};

        search.autocomplete( {
            delay: 200,
            minLength: 3,
            source: function( request, response ) {
                var term = request.term;
                request.action = "recw_autocomplete";
                request.recw_action = $("#recw_action").val();

                if ( term in cache ) {
                    response( cache[ term ] );
                    return;
                }

                $.ajax( {
                    type: "POST",
                    async: true,
                    cache: false,
                    url: window.ajaxurl || "admin-ajax.php",
                    data: request,
                    dataType: "json",
                    success: function( server_responce ) {
                        cache[ term ] = server_responce.data.terms;
                        response( server_responce.data.terms );
                        if ( server_responce.data.errors ) {
                            output.empty();
                            $.each( server_responce.data.errors, function( index, value ) {
                                output.append("<p>" + value + "</p>");
                            } );
                        }
                    }
                } );
            },
            select: function( event, ui ) {
                recw_search( ui.item.value );
            }

        } );

        function recw_search( term ) {

            search.autocomplete("close");

            output.empty().addClass("loading").append("<p>Searching for: " + term + "</p>");

            var data = {
                action: "recw_search",
                recw_action: $("#recw_action").val(),
                term: term
            };

            $.ajax( {
                type: "POST",
                async: true,
                cache: false,
                url: window.ajaxurl || "admin-ajax.php",
                data: data,
                dataType: "json",
                success: function( server_responce ) {

                    output.empty().removeClass("loading");

                    if ( server_responce.data.errors.length > 0 ) {

                        $.each( server_responce.data.errors, function( index, value ) {
                            output.append("<p>" + value + "</p>");
                        } );

                    } else {

                        if ( server_responce.data.items.length > 0 ) {
                            var list = $("<ol></ol>");
                            $.each( server_responce.data.items, function( index, item ) {
                                var excerpt = item.excerpt ? item.excerpt : "";
                                list.append("<li><a href=" + item.edit_link + ">" + item.title + "</a>" + excerpt + "</li>");
                            } );
                            output.append(list);
                        } else {
                            output.append("<p>Nothing found for " + term + ".</p>");
                        }

                    }
                }
            } );

        }

        form.submit( function() {
            recw_search( this.elements["search-term"].value );
            return false;
        } );

        if ( search.val() !== "" ) {
            form.submit();
        }

    } );

})( jQuery, window );
