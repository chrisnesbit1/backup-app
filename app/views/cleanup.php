<h1>Cleanup</h1>
<form method="post">
<input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
<label>Type WIPE to confirm <input name="confirm"></label><br>
<label><input type="checkbox" name="wipe_db" value="1"> Remove database</label><br>
<button type="submit">Cleanup</button>
</form>
