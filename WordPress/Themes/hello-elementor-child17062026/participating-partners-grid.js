jQuery( document ).ready(function($) {
    $( ".participating-partners-item" ).on( "click", function() {
        $('#geschaefte-modal-wrapper').css({'display': 'block'});

        $('#participating-partners-loading-animation').css({'display': 'block'});
        $('#geschaefte-modal-container #geschaefte-modal-content-container').css({'display': 'none'});

        let id = $(this).data('tmid');
        let ajaxReq = $.ajax( { url : 'https://backend.mycity.cards/api/v1/partners/' + id,
            contentType : 'application/json',
            dataType : 'json',
            headers: { "Accepts": "application/json; charset=utf-8", "X-API-KEY": window.request_data.x_api_key }
        });
        ajaxReq.success( function ( data, status, jqXhr ) {
            $('#geschaefte-modal-logo').attr('src', data.logoUrl);
            $('#geschaefte-modal-companyname').html(data.companyName);
            $('#geschaefte-modal-permanentBonusInfoText').html(data.permanentBonusInfoText);
            $('#geschaefte-modal-actionBonusInfoText').html(data.promotionalBonusInfoText);
            $('#geschaefte-modal-address').html(data.street + ', ' + data.zip + ' ' + data.city + ' ' + data.country);
            $('#geschaefte-modal-phone').html(data.phone);
            $('#geschaefte-modal-email').html('<a style="font-weight: normal; color: " href="mailto:' + data.email +  '">' + data.email + '</a>');
            $('#geschaefte-modal-website').attr('href', data.website);
            $('#geschaefte-modal-open-hours-additional-info').html(data.companyOpenHoursAdditionalInfo);

            if(data.closedMonday == true) {
                $( '#mon .geschaefte-modal-open-hours-times' ).append('geschlossen');
            } else {
                data.openingHours.mon.forEach((element, index) => {
                    if(index > 0) {
                        $( '#mon .geschaefte-modal-open-hours-times' ).append(', ');
                    }
                  $( '#mon .geschaefte-modal-open-hours-times' ).append(element.start + ' - ' + element.end);
                });
            }

            if(data.closedTuesday == true) {
                $( '#tue .geschaefte-modal-open-hours-times' ).append('geschlossen');
            } else {
                data.openingHours.tue.forEach((element, index) => {
                    if(index > 0) {
                        $( '#tue .geschaefte-modal-open-hours-times' ).append(', ');
                    }
                  $( '#tue .geschaefte-modal-open-hours-times' ).append(element.start + ' - ' + element.end);
                });
            }

            if(data.closedWednesday == true) {
                $( '#wed .geschaefte-modal-open-hours-times' ).append('geschlossen');
            } else {
                data.openingHours.wed.forEach((element, index) => {
                    if(index > 0) {
                        $( '#wed .geschaefte-modal-open-hours-times' ).append(', ');
                    }
                  $( '#wed .geschaefte-modal-open-hours-times' ).append(element.start + ' - ' + element.end);
                });
            }

            if(data.closedThursday == true) {
                $( '#thu .geschaefte-modal-open-hours-times' ).append('geschlossen');
            } else {
                data.openingHours.thu.forEach((element, index) => {
                    if(index > 0) {
                        $( '#thu .geschaefte-modal-open-hours-times' ).append(', ');
                    }
                  $( '#thu .geschaefte-modal-open-hours-times' ).append(element.start + ' - ' + element.end);
                });
            }

            if(data.closedFriday == true) {
                $( '#fri .geschaefte-modal-open-hours-times' ).append('geschlossen');
            } else {
                data.openingHours.fri.forEach((element, index) => {
                    if(index > 0) {
                        $( '#fri .geschaefte-modal-open-hours-times' ).append(', ');
                    }
                  $( '#fri .geschaefte-modal-open-hours-times' ).append(element.start + ' - ' + element.end);
                });
            }

            if(data.closedSaturday == true) {
                $( '#sat .geschaefte-modal-open-hours-times' ).append('geschlossen');
            } else {
                data.openingHours.sat.forEach((element, index) => {
                    if(index > 0) {
                        $( '#sat .geschaefte-modal-open-hours-times' ).append(', ');
                    }
                  $( '#sat .geschaefte-modal-open-hours-times' ).append(element.start + ' - ' + element.end);
                });
            }

            if(data.closedSunday == true) {
                $( '#sun .geschaefte-modal-open-hours-times' ).append('geschlossen');
            } else {
                data.openingHours.sun.forEach((element, index) => {
                    if(index > 0) {
                        $( '#sun .geschaefte-modal-open-hours-times' ).append(', ');
                    }
                  $( '#sun .geschaefte-modal-open-hours-times' ).append(element.start + ' - ' + element.end);
                });
            }

            $('#participating-partners-loading-animation').css({'display': 'none'});
            $('#geschaefte-modal-container #geschaefte-modal-content-container').css({'display': 'flex'});
        });
        ajaxReq.error( function ( jqXhr, textStatus, errorMessage ) {
            console.log('error:' + errorMessage);
        });
    });

    function closeGeschaefteModal() {
        $('#geschaefte-modal-logo').attr('src', '');
        $('#geschaefte-modal-companyname').html('');
        $('#geschaefte-modal-permanentBonusInfoText').html('');
        $('#geschaefte-modal-actionBonusInfoText').html('');
        $('#geschaefte-modal-address').html('');
        $('#geschaefte-modal-phone').html('');
        $('#geschaefte-modal-email').html('');
        $('#geschaefte-modal-website').attr('href', '#');
        $('#geschaefte-modal-open-hours-additional-info').html('');
        $('.geschaefte-modal-open-hours-times').html('');
        $('#geschaefte-modal-wrapper').css({'display': 'none'});
    }

    $( "#close-geschaefte-modal-button" ).on( "click", function() {
        closeGeschaefteModal();
    });

    $( "#geschaefte-modal-wrapper" ).on( "click", function() {
        closeGeschaefteModal();
    });

    $( "#geschaefte-modal-container" ).on( "click", function(event) {
         event.stopPropagation();
    });


    $( ".participating-partners-filter-item" ).on( "click", function() {
        $( ".participating-partners-filter-item" ).removeClass('filter-active');
        $(this).addClass('filter-active');
        let categoryToFilter = $(this).data('category');
        if(categoryToFilter == 'all') {
            $( ".participating-partners-item" ).each(function( index ) {
                $(this).css('display', 'flex');
            });
        } else {
            $( ".participating-partners-item" ).each(function( index ) {
              if($(this).data('category').includes(categoryToFilter)) {
                $(this).css('display', 'flex');
              } else {
                $(this).css('display', 'none');
              }
            });
        }
    });
});