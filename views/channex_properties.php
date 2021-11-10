<div class="app-page-title">
    <div class="page-title-wrapper head-fix-wep">
        <div class="page-title-heading">
            <h2><?php echo l('channex_integration/Channex Mapping with Minical', true);?> </h2>
        </div>
        <div class="page-title-actions">
            <?php if (isset($channex_room_types_rate_plans) && count($channex_room_types_rate_plans) > 0): ?>
                    <button type="button" class='btn btn-primary import-booking pull-right' channex_id="<?=$channex_id;?>"><?php echo l("channex_integration/Import OTA Booking Manually", true);?></button>
                <?php endif; ?>
        </div>
    </div>
</div>

<div class="settings integrations">
    <div class="col-md-12" >
        <?php if (isset($channex_room_types_rate_plans) && count($channex_room_types_rate_plans) > 0): ?>

        <div class="col-md-6">
            <div class="position-relative form-group">
                <?php foreach ($properties['data'] as $property):
                    if($channex_room_types[0]['ota_property_id'] == $property['attributes']['id']): ?>
                    <h3 id="property_id" data-prop_id="<?php echo $property['attributes']['id']; ?>"><?php echo $property['attributes']['title']; ?></h3>
                <?php endif; endforeach; ?>
            </div>
        </div>

        <div class="col-md-6" style="margin: 1px;">
            <div class="position-relative form-group">
                <label for="exampleZip" class=""><?php echo l('channex_integration/Rate Update Type'); ?></label>
                <select name="rate_type" class="form-control rate_type">
                    <option value="OBP" <?php if($channex_room_types[0]['rate_update_type'] == 'OBP') { echo 'selected'; } ?> >Occupancy Based Pricing</option>
                    <option value="PRP" <?php if($channex_room_types[0]['rate_update_type'] == 'PRP') { echo 'selected'; } ?> >Per-Room Pricing</option>
                </select>
            </div>
        </div>

        <?php else: ?>

        <div class="col-md-6">
            <div class="position-relative form-group">
                <label for="exampleZip" class=""><?php echo l('channex_integration/Channex Properties'); ?></label>
                <select name="property" class="form-control properties">
                    <option 
                        value="">-- Select Property --</option>
                    <?php foreach ($properties['data'] as $property): ?>
                        <option 
                        value="<?php echo $property['attributes']['id']; ?>"
                        <?php echo $channex_room_types[0]['channex_property_id'] == $property['attributes']['id'] ? 'SELECTED' : ''; ?>
                        ><?php echo $property['attributes']['title']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>


        <div class="col-md-6">
            <div class="position-relative form-group">
                <label for="exampleZip" class=""><?php echo l('channex_integration/Rate Update Type'); ?></label>
                <select name="rate_type" class="form-control rate_type">
                    <option value="OBP" selected >Occupancy Based Pricing</option>
                    <option value="PRP">Per-Room Pricing</option>
                </select>
            </div>
        </div>

    <?php endif; ?>

    <?php if (isset($channex_room_types_rate_plans) && count($channex_room_types_rate_plans) > 0): ?>
        <button type="button" class='btn btn-success full-sync-channex pull-right' channex_id="<?=$channex_id;?>"><?php echo l("Full Sync");?></button>
    <?php endif; ?>

    <form class="save_channex_mapping" >
        <div class="col-md-12 col-lg-12 col-xs-12 col-sm-12" style="margin-top: 20px;">
    <?php if (isset($channex_room_types_rate_plans) && count($channex_room_types_rate_plans) > 0):
        foreach ($channex_room_types_rate_plans as $value) :  ?>
    
    <div class="panel panel-default room-type-panel channex_room_types <?php echo $is_mapping ? '' : 'hidden'; ?>" data-channex_room_id="<?php echo $value['room_type_id']; ?>">
        <div class="panel-heading">
            <span class="channex-room-type" >
                <?php echo $value['room_type_name']; ?> (<?php echo $value['room_type_id']; ?>)
            </span>
            <span class="minical-room-type">
                <?php
                    if (isset($minical_room_types)):
                ?>
                        <select name="minical_room_type" class="channex_manager">
                            <option value=""><?php echo l("channex_integration/Not selected", true); ?></option>
                            <?php
                                foreach($minical_room_types as $room_type)
                                { ?>
                                    <option value="<?php echo $room_type['id']; ?>" <?php echo $value['minical_room_type_id'] == $room_type['id'] ? 'SELECTED' : ''; ?>>
                                        <?php echo $room_type['name']; ?>
                                    </option>
                            <?php }
                            ?>
                        </select>
                <?php
                    endif;
                ?>
            </span>
        </div>
        <div class="panel-body">
            <?php if(isset($value['rate_plans']) && count($value['rate_plans']) > 0):
                foreach($value['rate_plans'] as $val): ?>
            <div class="rate-plan">
                <span class="channex-rate-plan" data-channex_room_type_id="<?php echo $value['room_type_id']; ?>" data-channex_rate_id="<?php echo $value['room_type_id'].'_'.$val['rate_plan_id']; ?>">
                    <?php echo $val['rate_plan_name']; ?> (<?php echo $val['rate_plan_id']; ?>)
                </span>
                <span class="minical-rate-plan">
                    <?php
                        if (isset($minical_rate_plans)):
                    ?>
                            <select name="minical_rate_plan" class="channex_manager" data-ch_id="<?php echo $val['rate_plan_id']; ?>" >
                                <option value=""><?php echo l("channex_integration/Not selected", true); ?></option>
                                <?php
                                    foreach($minical_rate_plans as $rate_plan)
                                    {
                                        $room_type_id = $rate_plan['room_type_id']; 
                                    ?>
                                        <option style="display: none;" data-room_type_id="<?php echo $room_type_id; ?>" value="<?php echo $rate_plan['rate_plan_id']; ?>" <?php echo isset($val['minical_rate_plan_id']) && $val['minical_rate_plan_id'] == $rate_plan['rate_plan_id'] ? 'SELECTED' : ''; ?>>
                                            <?php echo $rate_plan['rate_plan_name']; ?>
                                        </option>
                                <?php    }
                                ?>
                            </select>
                    <?php
                        endif;
                    ?>
                </span>
            </div> <!-- /Rate Plans -->
        <?php endforeach; endif; ?>
        </div>
    </div>
<?php endforeach;  ?>
</div>
<div class="col-md-2">
    <input type="button" class="btn btn-success save_channex_mapping_button" value="<?php echo l('channex_integration/Save All', true); ?>"/>
</div>
<?php endif; ?>



        </form>
    </div>
</div>
