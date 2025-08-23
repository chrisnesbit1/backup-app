<h1>Install</h1>
<form method="post">
<input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
<label>S3 Endpoint <input name="s3_endpoint" required></label><br>
<label>S3 Region <input name="s3_region" required></label><br>
<label>Access Key <input name="s3_access_key" required></label><br>
<label>Secret Key <input name="s3_secret_key" required></label><br>
<label>Bucket <input name="bucket" required></label><br>
<label>Storage <select name="storage">
    <option value="S3_ONLY">S3 Only</option>
    <option value="LOCAL_AND_S3">Local + S3</option>
</select></label><br>
<button type="submit">Install</button>
</form>
