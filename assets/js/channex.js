$(document).ready(function(){

    $('.manage-channel').on("click", function() {
        var channexId = $(this).data('channex_id');
        window.location.href = getBaseURL() + 'channex_properties/' + channexId
    });

    $(".edit-channel-configuration").on("click", function() {
        $('#manage-channex').addClass('hidden');
        $('#configure-channex').removeClass('hidden');
    });

    $('body').on("click", ".deconfigure-channel", function() {
        var channexId = $(this).data('channex_id');
        $.ajax({
            type: "POST",
            url: getBaseURL() + "deconfigure_channex_AJAX",
            dataType: 'json',
            data: {
                channex_id: channexId
            },
            success: function (response) {
                if(response.success){
                    alert(l('Channel Configuration Updated!'));
                    location.reload();
                }
            }
        });    
    });

    $('body').on('click', '.full-sync-channex',function() {
        var button = $(this);
        button.prop('disabled', true);
            $.post(getBaseURL() + 'update_full_refresh',
            {},
                function(res) {
                    button.prop('disabled', false);
                }
            ).always(function(res) {
            alert(l('Data refreshed successfully!'));
        });
    });

    $('body').on('click','.login-channex',function(){
        var user_api_key = $('input[name="user_api_key"]').val();
        
        if(user_api_key == ''){
            alert(l('Please enter User API Key', true));
        }
          else {
            $.ajax({
                type    : "POST",
                dataType: 'json',
                url     : getBaseURL() + 'signin_channex',
                data: {user_api_key : user_api_key},
                success: function( data ) {
                    if(data.success){
                        window.location.href = getBaseURL() + 'channex_properties/' + data.channex_id
                    } else {
                        alert(data.msg);
                    }
                }
            });
        }
    });

    $('body').on('change','.properties',function(){
        var property_id = $(this).val();

        if(property_id == ''){
            alert(l('Please select any property', true));
        } else {
            var pathArray = window.location.pathname.split( '/' );
            console.log('pathArray',pathArray);
            console.log('pathArray last',pathArray[pathArray.length-1]);

            $.ajax({
                type    : "POST",
                dataType: 'html',
                url     : getBaseURL() + 'get_room_types',
                data: {property_id : property_id, channex_id : pathArray[pathArray.length-1]},
                success: function( data ) {
                    $('.save_channex_mapping').html(data);
                }
            });
        }
    });

    $('body').on('change','select[name="minical_room_type"]',function(){
        $('div.channex_room_types').each(function(){
            var room_type_id = $(this).find('select[name="minical_room_type"]').val();
            $(this).find('select[name="minical_rate_plan"]').find('option').hide();
            $(this).find('select[name="minical_rate_plan"]').find('option:first-child').show();
            $(this).find('select[name="minical_rate_plan"]').find('option[data-room_type_id="'+room_type_id+'"]').show();
        });
    });

    $('body').on('click', '.save_channex_mapping_button', function(){

        $(this).val('Loading..').attr('disabled', true);
        var mappingData = [];
        var mappingDataRP = [];
        var propertyId = $('select[name="property"]').val();
        var rateType = $('select[name="rate_type"]').val();
        if(propertyId == undefined){
            propertyId = $('#property_id').data('prop_id');
        }

        $('.channex_room_types').each(function(){
            var chRoomTypeId = $(this).data('channex_room_id');
            mappingData.push({
                "channex_room_type_id": $(this).data('channex_room_id'),
                "minical_room_type_id": $(this).find('select[name="minical_room_type"]').val()
            });
            
        });

        $('.rate-plan').each(function(){
            var minRPId = $(this).find('select[name="minical_rate_plan"]').val();
                mappingDataRP.push({
                    "channex_rate_plan_id": $(this).find('.channex-rate-plan').data('channex_rate_id'),
                    "minical_rate_plan_id": $(this).find('select[name="minical_rate_plan"]').val(),
                    "rate_update_type": $(this).find('select[name="rate_type"]').val()
                });
        });
        
        var pathArray = window.location.pathname.split( '/' );

        $.ajax({
            url    : getBaseURL() + "save_channex_mapping_AJAX",
            type   : "POST",
            dataType: "json",
            data   : {
                        channex_id : pathArray[pathArray.length-1],
                        property_id : propertyId, 
                        mapping_data : mappingData,
                        mapping_data_rp : mappingDataRP,
                        rate_type : rateType
                    },
            success: function (data) {
                console.log(data);
                if(data.success){
                    $('.save_channex_mapping_button').val('Save All').attr('disabled', false);
                    // updateRates("","","");
                    alert(l('Channel Configuration Updated!'));
                    location.reload();
                }
            },
            error: function (data, error) {
                console.log(data);
                $('.save_channex_mapping_button').val('Save All').attr('disabled', false);
                location.reload();
            }
        });
        return false;
    });

    $(".import-booking").on("click", function () {

        imageUrl = getBaseURL() + 'images/loading.gif',

        importBookingFormHtml = '<div class="modal fade" id="import_booking">'+
                                '<div class="modal-dialog" role="document">'+
                                    '<div class="modal-content">'+
                                        '<div class="modal-body">'+
                                            '<form id="import_booking_form">'+
                                                '<div class="form-group">'+
                                                    '<label for="revision-id" class="col-form-label">'+l('channex_integration/Booking Revision ID')+'</label>'+
                                                    '<span class="required" style="color:red;">*</span>'+
                                                    '<input type="text" class="form-control" id="revision_id" name="revision_id">'+
                                                '</div>'+
                                            '</form>'+
                                            '<div style="font-size: 14px;">'+
                                                '<small><b>'+l('channex_integration/Note')+': </b>'+
                                                    l("channex_integration/You can find the <b>Booking Revision ID</b> from your channex account.<br/>Login to Channex account -> Select Property -> Bookings -> Click on View link for particular booking.<br/> Ex. Revision ID: d5a876f8-xxxx-xxxx-xxxx-4c75a3c2b5a0", true)+
                                                '</small>'+
                                            '</div>'+
                                        '</div>'+
                                        '<div class="modal-footer">'+
                                            '<span id="loading_avail_img" style="display:none;">'+
                                                '<img class="loader-img" src="'+imageUrl+'"  style="width: 5%;margin: 0px 10px;"/>'+
                                            '</span>'+
                                            '<button type="button" class="btn btn-primary save_booking">'+l('channex_integration/Save')+'</button>'+
                                            '<button type="button" class="btn btn-secondary" data-dismiss="modal">'+l('channex_integration/Close')+'</button>'+
                                        '</div>'+
                                    '</div>'+
                                '</div>'+
                            '</div>';
        $('body').append(importBookingFormHtml);
        $('#import_booking').modal('show');                    
    });

    $('body').on('click', '.save_booking', function(){

        var revision_id = $('#revision_id').val();
        var validate = '';

        if(revision_id == ''){
            validate = validate+"\n"+l('channex_integration/Revision ID required');
        }
        
        if(validate == ''){

            $('#loading_avail_img').show();
            $(this).prop('disabled', true);

            $.ajax({
                type: "POST",
                url: getBaseURL() + 'cron/channex_retrieve_booking/'+revision_id,
                data: {},
                success: function(response)
                {
                    console.log('response', response);
                    if(response && response.search("already exists") != -1){
                        alert(l("channex_integration/Booking already exists"));
                    } else if(response && response.search("created successfully") != -1){
                        alert(l("channex_integration/Booking created successfully"));
                    } else if(response && response.search('wrong id') != -1){
                        alert(l("channex_integration/Please enter valid revesion ID"));
                    }

                    $(this).prop('disabled', false);
                    $('#loading_avail_img').hide();
                    location.reload();
                }
            });
        }
        else{
            alert(validate);
        }
    });
    
});

function showRatePlansByRoomType (){
    $('div.channex_room_types').each(function(){
        var room_type_id = $(this).find('select[name="minical_room_type"]').val();
        $(this).find('select[name="minical_rate_plan"]').find('option').hide();
        $(this).find('select[name="minical_rate_plan"]').find('option:first-child').show();
        $(this).find('select[name="minical_rate_plan"]').find('option[data-room_type_id="'+room_type_id+'"]').show();
    });
}

showRatePlansByRoomType();