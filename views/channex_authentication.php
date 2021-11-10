<div class="settings integrations">
	<div class="page-header">
		<h2>
			<?php echo l('Channex'); ?>
		</h2>
	</div>

    <div class="panel panel-default ">
        <?php if($channex_data): ?>
        <div class="panel-body form-horizontal ">
            <div id="configure-channex" class="hidden">
                <div class="form-group rate-group text-center">
                    <label for="channex_email" class="col-sm-3 control-label">
                        <span alt="channex_email" title="channex_email"><?=l("channex_integration/Channex Email");?></span>
                    </label>
                    <div class="col-sm-9">
                        <input type="text" name="email" class="form-control" value="<?php echo isset($channex_data['email']) ? $channex_data['email'] : ''; ?>">
                    </div>
                </div>
                <div class="form-group rate-group text-center">
                    <label for="channex_password" class="col-sm-3 control-label">
                        <span alt="channex_password" title="channex_password"><?=l("channex_integration/Channex Password");?></span>
                    </label>
                    <div class="col-sm-9">
                        <input type="password" name="password" class="form-control" value="<?php echo isset($channex_data['password']) ? $channex_data['password'] : ''; ?>">
                    </div>
                </div>

                <div class="text-center">
                    <button type="button" class="btn btn-success login-channex" ><?=l("channex_integration/Sign in");?></button>
                </div>
            </div>
            <div id="manage-channex" class="">
                <div class="text-center">
                    <button class="btn btn-success manage-channel" data-channex_id="<?php echo isset($channex_data['id']) ? $channex_data['id'] : ''; ?>">Map Room Types &amp; Rates</button>
                    <button class="btn btn-warning edit-channel-configuration">Account Setup</button>
                    <button class="btn btn-danger deconfigure-channel" data-channex_id="<?php echo isset($channex_data['id']) ? $channex_data['id'] : ''; ?>">De-Configure</button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="panel-body form-horizontal ">
            <div id="configure-channex" class="">
                <div class="form-group rate-group text-center">
                    <label for="channex_email" class="col-sm-3 control-label">
                        <span alt="channex_email" title="channex_email"><?=l("channex_integration/Channex Email");?></span>
                    </label>
                    <div class="col-sm-9">
                        <input type="text" name="email" class="form-control" value="<?php echo isset($channex_data['email']) ? $channex_data['email'] : ''; ?>">
                    </div>
                </div>
                <div class="form-group rate-group text-center">
                    <label for="channex_password" class="col-sm-3 control-label">
                        <span alt="channex_password" title="channex_password"><?=l("channex_integration/Channex Password");?></span>
                    </label>
                    <div class="col-sm-9">
                        <input type="password" name="password" class="form-control" value="<?php echo isset($channex_data['password']) ? $channex_data['password'] : ''; ?>">
                    </div>
                </div>

                <div class="text-center">
                    <button type="button" class="btn btn-success login-channex" ><?=l("channex_integration/Sign in");?></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    </div>
</div>
