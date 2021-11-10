// Add an event listener for create booking
document.addEventListener("booking_created", function(e) {
    if(e && e.detail)
    {
        if(e.detail.booking_room_data && e.detail.booking_room_data.length > 1){
            // console.log(e.detail.booking_room_data);
            $.each(e.detail.booking_room_data, function(i,v){
                // if(v.room_count && v.room_count > 0){
                    $.ajax({
                        type    : "POST",
                        dataType: 'json',
                        url     : getBaseURL() + 'channex_update_availability',
                        data: {
                                booking_id : v.booking_id,
                                check_in_date : v.check_in_date,
                                check_out_date : v.check_out_date,
                                room_type_id : v.room_type_id
                            },
                        success: function( resp ) {
                            console.log(resp);
                        }
                    });
                // }
            });
        } else {
            $.ajax({
                type    : "POST",
                dataType: 'json',
                url     : getBaseURL() + 'channex_update_availability',
                data: {
                        booking_id : e.detail.reservation_id,
                        check_in_date : e.detail.booking_data.check_in_date,
                        check_out_date : e.detail.booking_data.check_out_date,
                        room_type_id : e.detail.booking_room_data.room_type_id
                    },
                success: function( resp ) {
                    console.log(resp);
                }
            });
        }
        
    }
});

// Add an event listener for delete booking
document.addEventListener("booking_deleted", function(e) {
    if(e && e.detail && e.detail.reservation_id)
    {
        console.log(e.detail);
        $.ajax({
            type    : "POST",
            dataType: 'json',
            url     : getBaseURL() + 'channex_update_availability',
            data: {
                    booking_id : e.detail.reservation_id,
                    check_in_date : e.detail.booking_data.check_in_date,
                    check_out_date : e.detail.booking_data.check_out_date,
                    room_type_id : e.detail.booking_data.booking_blocks[0].room_type_id
                },
            success: function( resp ) {
                console.log(resp);
            }
        });
    }
});

// Add an event listener for update booking
document.addEventListener("booking_updated", function(e) {
    if(e && e.detail && e.detail.reservation_id)
    {
        console.log(e.detail);
        $.ajax({
            type    : "POST",
            dataType: 'json',
            url     : getBaseURL() + 'channex_update_availability',
            data: {
                    booking_id : e.detail.reservation_id,
                    check_in_date : e.detail.booking_data.rooms[0].check_in_date,
                    check_out_date : e.detail.booking_data.rooms[0].check_out_date,
                    room_type_id : e.detail.booking_data.rooms[0].room_type_id
                },
            success: function( resp ) {
                console.log(resp);
            }
        });
    }
});

// Add an event listener for rate create
document.addEventListener("rate_created", function(e) {

    if(e && e.detail.rate_data && e.detail.rate_data.rate_plan_id)
    {
        var rates = e.detail.rate_data;
        console.log(rates);
        $.ajax({
            type    : "POST",
            dataType: 'json',
            url     : getBaseURL() + 'channex_update_restrictions',
            data: {
                    rate_plan_id : rates.rate_plan_id,
                    date_start : rates.date_start,
                    date_end : rates.date_end,
                    adult_1_rate : rates.adult_1_rate,
                    adult_2_rate : rates.adult_2_rate,
                    adult_3_rate : rates.adult_3_rate,
                    adult_4_rate : rates.adult_4_rate,
                    additional_adult_rate : rates.additional_adult_rate,
                    closed_to_arrival : rates.closed_to_arrival,
                    closed_to_departure : rates.closed_to_departure,
                    minimum_length_of_stay : rates.minimum_length_of_stay,
                    maximum_length_of_stay : rates.maximum_length_of_stay,
                    can_be_sold_online : rates.can_be_sold_online
                },
            success: function( resp ) {
                console.log(resp);
            }
        });
    }
});