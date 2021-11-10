<?php if (isset($channex_room_types_rate_plans) && count($channex_room_types_rate_plans) > 0) : ?>

<div class="col-md-12 col-lg-12 col-xs-12 col-sm-12" style="margin-top: 20px;">
    <?php foreach ($channex_room_types_rate_plans as $value) :  ?>
    
    <div class="panel panel-default room-type-panel channex_room_types" data-channex_room_id="<?php echo $value['room_type_id']; ?>">
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
                                    <option value="<?php echo $room_type['id']; ?>">
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
                                        <option style="display: none;" data-room_type_id="<?php echo $room_type_id; ?>" value="<?php echo $rate_plan['rate_plan_id']; ?>">
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
<?php endforeach; ?>
</div>
<div class="col-md-2">
    <input type="button" class="btn btn-success save_channex_mapping_button" value="<?php echo l('channex_integration/Save All', true); ?>"/>
</div>

<?php else: ?>
<div style="margin: 80px 15px;">
    <h3><?=l("channex_integration/No Room(s) found on channex.");?></h3>
</div>
<?php endif; ?>