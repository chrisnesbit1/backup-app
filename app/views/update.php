<h1>Update Config</h1>
<form method="post">
<input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
<label>S3 Endpoint <input name="s3_endpoint" value="<?php echo htmlspecialchars($config['s3_endpoint'] ?? '', ENT_QUOTES); ?>" required></label><br>
<label>S3 Region <input name="s3_region" value="<?php echo htmlspecialchars($config['s3_region'] ?? '', ENT_QUOTES); ?>" required></label><br>
<label>Access Key <input name="s3_access_key" value="<?php echo htmlspecialchars($config['s3_access_key'] ?? '', ENT_QUOTES); ?>" required></label><br>
<label>Secret Key <input name="s3_secret_key" value="<?php echo htmlspecialchars($config['s3_secret_key'] ?? '', ENT_QUOTES); ?>" required></label><br>
<label>Bucket <input name="bucket" value="<?php echo htmlspecialchars($config['bucket'] ?? '', ENT_QUOTES); ?>" required></label><br>
<label>Storage <select name="storage">
    <option value="S3_ONLY" <?php echo (($config['storage'] ?? '') === 'S3_ONLY') ? 'selected' : ''; ?>>S3 Only</option>
    <option value="LOCAL_AND_S3" <?php echo (($config['storage'] ?? '') === 'LOCAL_AND_S3') ? 'selected' : ''; ?>>Local + S3</option>
</select></label><br>
<button type="submit">Save</button>
</form>

<form method="post" style="margin-top:1em;">
<input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
<button type="submit" name="fix" value="1">Fix Permissions</button>
</form>
