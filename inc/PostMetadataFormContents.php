<?php
/**
 * Created by claudio on 2018-12-15
 */
?>
<style>
    label {
        font-weight:600
    }
    .meta-table th {
        padding:5px
    }
    .meta-table td {
        padding:5px
    }
</style>
<label for="ctn_load_client">Load Catenis API Client</label>&nbsp;&nbsp;
<input type="checkbox" id="ctn_load_client" name="_ctn_api_client[ctn_load_client]" onclick="ctnShowApiClientSettings(this.checked)"
    <?php echo !empty($postMetadata['ctn_load_client']) ? checked($postMetadata['ctn_load_client'], 'on', false) : ''; ?>>
<div id="divCtnApiClientSetting" style="display:none">
    <p style="font-size:110%">Override global settings<br><span style="color:gray">(leave field blank to use the corresponding global setting)</span></p>
    <h2 style="font-weight:600;padding:0;margin-top:0.5em">Client Credentials</h2>
    <p>Credentials of Catenis device to use with the Catenis API client</p>
    <p>
        <a id="lnkShowCredentials" style="display:none" href="javascript:" onclick="javascript:ctnShowDeviceCredentials(true);return false">Enter credentials</a>
        <a id="lnkHideCredentials" style="display:none" href="javascript:" onclick="javascript:ctnShowDeviceCredentials(false);return false">Clear credentials</a>
    </p>
    <table id="tblDevCredentials" class="form-table meta-table" style="display:none">
        <tbody>
        <tr>
            <th scope="row">
                <label for="ctn_device_id">Device ID</label>
            </th>
            <td>
                <input type="text" id="ctn_device_id" name="_ctn_api_client[ctn_device_id]" class="regular-text" maxlength="20" autocomplete="off"
                        <?php echo !empty($postMetadata['ctn_device_id']) ? 'value="' . esc_attr($postMetadata['ctn_device_id']) . '"' : '' ?>>
                <script>document.getElementById('ctn_device_id').disabled = true</script>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="ctn_api_access_secret">API Access Secret</label>
            </th>
            <td>
                <input type="password" id="ctn_api_access_secret" name="_ctn_api_client[ctn_api_access_secret]" class="regular-text" maxlength="128" autocomplete="off"
                        <?php echo !empty($postMetadata['ctn_api_access_secret']) ? 'value="' . esc_attr($postMetadata['ctn_api_access_secret']) . '"' : '' ?>>
                <script>document.getElementById('ctn_api_access_secret').disabled = true</script>
            </td>
        </tr>
        </tbody>
    </table>
    <h2 style="font-weight:600;padding:0;margin-top:1.5em">Client Options</h2>
    <p>Enter the options for instantiating the Catenis API client</p>
    <table class="form-table meta-table">
        <tbody>
        <tr>
            <th scope="row">
                <label for="ctn_host">Host</label>
            </th>
            <td>
                <input type="text" id="ctn_host" name="_ctn_api_client[ctn_host]" class="regular-text" maxlength="80" autocomplete="off"
                        <?php echo !empty($postMetadata['ctn_host']) ? 'value="' . esc_attr($postMetadata['ctn_host']) . '"' : '' ?>>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="ctn_environment">Environment</label>
            </th>
            <td>
                <select id="ctn_environment" name="_ctn_api_client[ctn_environment]">
                    <option value=""></option>
                    <option value="prod" <?php echo !empty($postMetadata['ctn_environment']) ? selected($postMetadata['ctn_environment'], 'prod', false) : ''; ?>>Production</option>
                    <option value="sandbox" <?php echo !empty($postMetadata['ctn_environment']) ? selected($postMetadata['ctn_environment'], 'sandbox', false) : ''; ?>>Sandbox</option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="ctn_secure">Secure Connection</label>
            </th>
            <td>
                <select id="ctn_secure" name="_ctn_api_client[ctn_secure]">
                    <option value=""></option>
                    <option value="on" <?php echo !empty($postMetadata['ctn_secure']) ? selected($postMetadata['ctn_secure'], 'on', false) : ''; ?>>On</option>
                    <option value="off" <?php echo !empty($postMetadata['ctn_secure']) ? selected($postMetadata['ctn_secure'], 'off', false) : ''; ?>>Off</option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="ctn_compress_threshold">Compression Threshold</label>
            </th>
            <td>
                <input type="number" id="ctn_compress_threshold" name="_ctn_api_client[ctn_compress_threshold]" class="regular-text" min="<?php echo self::$minCompressThreshold ?>" step="32"
                        <?php echo !empty($postMetadata['ctn_compress_threshold']) ? 'value="' . esc_attr($postMetadata['ctn_compress_threshold']) . '"' : '' ?>>
            </td>
        </tr>
        </tbody>
    </table>
</div>
<script type="application/javascript">
    if (document.getElementById('ctn_load_client').checked) ctnShowApiClientSettings(true);
    function ctnShowApiClientSettings(show) {
        document.getElementById('divCtnApiClientSetting').style.display = show ? 'block' : 'none';
    }
    function ctnShowDeviceCredentials(show) {
        var fieldDeviceId = document.getElementById('ctn_device_id');
        var fieldApiAccessSecret = document.getElementById('ctn_api_access_secret');

        if (show) {
            fieldDeviceId.disabled = false;
            fieldApiAccessSecret.disabled = false;
            document.getElementById('tblDevCredentials').style.display = 'block';
            document.getElementById('lnkShowCredentials').style.display = 'none';
            document.getElementById('lnkHideCredentials').style.display = 'inline';
        }
        else {
            document.getElementById('tblDevCredentials').style.display = 'none';
            fieldDeviceId.value = '';
            fieldApiAccessSecret.value = '';
            fieldDeviceId.disabled = true;
            fieldApiAccessSecret.disabled = true;
            document.getElementById('lnkShowCredentials').style.display = 'inline';
            document.getElementById('lnkHideCredentials').style.display = 'none';
        }
    }
    ctnShowDeviceCredentials(<?php echo !empty($postMetadata['ctn_device_id']) || !empty($postMetadata['ctn_api_access_secret']) ?
        'true' : 'false' ?>);
</script>
