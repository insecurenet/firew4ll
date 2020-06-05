<?php
/*
 * services_acb.php
 *
 * part of Firew4LL (https://www.firew4ll.com)
 * Copyright (c) 2020 InSecureNet, SRL (ISN)
 * 
 * forked from pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2015 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-services-acb
##|*NAME=Services: Auto Config Backup: Restore
##|*DESCR=Restore from auto config backup.
##|*MATCH=services_acb.php*
##|-PRIV

require("guiconfig.inc");
require("acb.inc");

// Separator used during client / server communications
$oper_sep = "\|\|";
$exp_sep = '||';

// Encryption password
$decrypt_password = $config['system']['acb']['encryption_password'];

// Defined username. Username must be sent lowercase. See Redmine #7127 and Netgate Redmine #163
$username = strtolower($config['system']['acb']['gold_username']);
$password = $config['system']['acb']['gold_password'];

// URL to restore.php
$get_url = "https://portal.pfsense.org/pfSconfigbackups/restore.php";

// URL to stats
$stats_url = "https://portal.pfsense.org/pfSconfigbackups/showstats.php";

// URL to delete.php
$del_url = "https://portal.pfsense.org/pfSconfigbackups/delete.php";

// Set hostname
if ($_REQUEST['hostname']) {
	$hostname = $_REQUEST['hostname'];
} else {
	$hostname = $config['system']['hostname'] . "." . $config['system']['domain'];
}

// Hostname of local machine
$myhostname = $config['system']['hostname'] . "." . $config['system']['domain'];

if (!$decrypt_password) {
	Header("Location: /services_acb_settings.php");
	exit;
}

if ($_REQUEST['savemsg']) {
	$savemsg = htmlentities($_REQUEST['savemsg']);
}

if ($_REQUEST['download']) {
	$pgtitle = array("Servizi", "Auto Configurazione Backup", "Info Revisioni");
} else {
	$pgtitle = array("Servizi", "Auto Configurazione Backup", "Ripristina");
}

/* Set up time zones for conversion. See #5250 */
$acbtz = new DateTimeZone('America/Chicago');
$mytz = new DateTimeZone(date_default_timezone_get());

include("head.inc");

function get_hostnames() {
	global $stats_url, $username, $password, $oper_sep, $config, $g, $exp_sep;
	// Populate available backups
	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, $stats_url);
	curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_POST, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "action=showstats");
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());

	$data = curl_exec($curl_session);
	if (curl_errno($curl_session)) {
		$fd = fopen("/tmp/acb_statsdebug.txt", "w");
		fwrite($fd, $stats_url . "" . "action=showstats" . "\n\n");
		fwrite($fd, $data);
		fwrite($fd, curl_error($curl_session));
		fclose($fd);
	} else {
		curl_close($curl_session);
	}

	// Loop through and create new confvers
	$data_split = explode("\n", $data);
	$statvers = array();
	foreach ($data_split as $ds) {
		$ds_split = explode($exp_sep, $ds);
		if ($ds_split[0]) {
			$statvers[] = $ds_split[0];
		}
	}
	return $statvers;
}

if ($_REQUEST['rmver'] != "") {
	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, "https://acb.netgate.com/rmbkp");
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "userkey=" . $userkey .
		"&revision=" . urlencode($_REQUEST['rmver']) .
		"&version=" . $g['product_version'] .
		"&uid=" . urlencode($uniqueID));
	curl_setopt($curl_session, CURLOPT_POST, 3);
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());

	$data = curl_exec($curl_session);
	if (curl_errno($curl_session)) {
		$fd = fopen("/tmp/acb_deletedebug.txt", "w");
		fwrite($fd, $get_url . "" . "action=delete&hostname=" . urlencode($hostname) . "&revision=" . urlencode($_REQUEST['rmver']) . "\n\n");
		fwrite($fd, $data);
		fwrite($fd, curl_error($curl_session));
		fclose($fd);
		$savemsg = "Si è verificato un errore mentre si cercava di rimuovere l'elemento da ";
	} else {
		curl_close($curl_session);
		$budate = new DateTime($_REQUEST['rmver'], $acbtz);
		$budate->setTimezone($mytz);
		$savemsg = "Backup revision " . htmlspecialchars($budate->format(DATE_RFC2822)) . " rimosso.";
	}
}

if ($_REQUEST['newver'] != "") {
	// Phone home and obtain backups
	$curl_session = curl_init();

	curl_setopt($curl_session, CURLOPT_URL, "https://acb.netgate.com/getbkp");
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "userkey=" . $userkey .
		"&revision=" . urlencode($_REQUEST['newver']) .
		"&version=" . $g['product_version'] .
		"&uid=" . urlencode($uniqueID));
	curl_setopt($curl_session, CURLOPT_POST, 3);
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());
	$data = curl_exec($curl_session);
	$data_split = explode('++++', $data);
	$sha256 = trim($data_split[0]);
	$data = $data_split[1];

	if (!tagfile_deformat($data, $data, "config.xml")) {
		$input_errors[] = "Il file scaricato non sembra contenere una configurazione Firew4ll criptata.";
	}

	$out = decrypt_data($data, $decrypt_password);

	$pos = stripos($out, "</pfsense>");
	$data = substr($out, 0, $pos);
	$data = $data . "</pfsense>\n";

	$fd = fopen("/tmp/config_restore.xml", "w");
	fwrite($fd, $data);
	fclose($fd);

	if (strlen($data) < 50) {
		$input_errors[] = "Config.xml decifrato inferiore a 50 caratteri, qualcosa andato storto. Interrompere.";
	}

	$ondisksha256 = trim(shell_exec("/sbin/sha256 /tmp/config_restore.xml | /usr/bin/awk '{ print $4 }'"));
	// We might not have a sha256 on file for older backups
	if ($sha256 != "0" && $sha256 != "") {
		if ($ondisksha256 != $sha256) {
			$input_errors[] = "SHA256 values do not match, cannot restore. $ondisksha256 != $sha256";
		}
	}
	if (curl_errno($curl_session)) {
		/* If an error occured, log the error in /tmp/ */
		$fd = fopen("/tmp/acb_restoredebug.txt", "w");
		fwrite($fd, $get_url . "" . "action=restore&hostname={$hostname}&revision=" . urlencode($_REQUEST['newver']) . "\n\n");
		fwrite($fd, $data);
		fwrite($fd, curl_error($curl_session));
		fclose($fd);
	} else {
		curl_close($curl_session);
	}

	if (!$input_errors && $data) {
		conf_mount_rw();
		if (config_restore("/tmp/config_restore.xml") == 0) {
			$savemsg = "Configurazione di Firew4ll riportata con successo alla versione scelta" . urldecode($_REQUEST['newver']) . ".";
			$savemsg .= <<<EOF
			<br />
		<form action="diag_reboot.php" method="post">
			Reboot the firewall to full activate changes?
			<input name="override" type="hidden" value="yes" />
			<input name="Submit" type="submit" class="formbtn" value=" Yes " />
		</form>
EOF;
		} else {
			$savemsg = "Unable to revert to the selected configuration.";
		}
	} else {
		log_error("There was an error when restoring the AutoConfigBackup item");
	}
	unlink_if_exists("/tmp/config_restore.xml");
	conf_mount_ro();
}

if ($_REQUEST['download']) {
	// Phone home and obtain backups
	$curl_session = curl_init();

	curl_setopt($curl_session, CURLOPT_URL, "https://acb.netgate.com/getbkp");
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "userkey=" . $userkey . "&revision=" . urlencode($_REQUEST['download']));
	curl_setopt($curl_session, CURLOPT_POST, 3);
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);

	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());
	$data = curl_exec($curl_session);

	if (!tagfile_deformat($data, $data1, "config.xml")) {
		$input_errors[] = "Il file scaricato non sembra contenere una configurazione di Firew4ll criptata.";
	} else {
		$ds = explode('++++', $data);
		$revision = $_REQUEST['download'];
		$sha256sum = $ds[0];
		if ($sha256sum == "0") {
			$sha256sum = "None on file.";
		}
		$data = $ds[1];
		$configtype = "Encrypted";
		if (!tagfile_deformat($data, $data, "config.xml")) {
			$input_errors[] = "Il file scaricato non sembra contenere una configurazione di Firew4ll criptata.";
		}
		$data = decrypt_data($data, $decrypt_password);
		if (!strstr($data, "pfsense")) {
			$data = "Non riesco a decifrare. Chiave di cifratura diversa?";
			$input_errors[] = "Non riesco a decifrare config.xml";
		}
	}
}

// $confvers must be populated viewing info but there were errors
if ( !($_REQUEST['download']) || $input_errors) {
	// Populate available backups
	$curl_session = curl_init();

	curl_setopt($curl_session, CURLOPT_URL, "https://acb.netgate.com/list");
	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "userkey=" . $userkey .
		"&uid=eb6a4e6f76c10734b636" .
		"&version=" . $g['product_version'] .
		"&uid=" . urlencode($uniqueID));
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_POST, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);

	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());

	$data = curl_exec($curl_session);

	if (curl_errno($curl_session)) {
		$fd = fopen("/tmp/acb_backupdebug.txt", "w");
		fwrite($fd, $get_url . "" . "action=showbackups" . "\n\n");
		fwrite($fd, $data);
		fwrite($fd, curl_error($curl_session));
		fclose($fd);
	} else {
		curl_close($curl_session);
	}

	// Loop through and create new confvers
	$data_split = explode("\n", $data);

	$confvers = array();

	foreach ($data_split as $ds) {
		$ds_split = explode($exp_sep, $ds);
		$tmp_array = array();
		$tmp_array['username'] = $ds_split[0];
		$tmp_array['reason'] = $ds_split[1];
		$tmp_array['time'] = $ds_split[2];

		/* Convert the time from server time to local. See #5250 */
		$budate = new DateTime($tmp_array['time'], $acbtz);
		$budate->setTimezone($mytz);
		$tmp_array['localtime'] = $budate->format(DATE_RFC2822);

		if ($ds_split[2] && $ds_split[0]) {
			$confvers[] = $tmp_array;
		}
	}
}

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[0] = array("Impostazioni", false, "/services_acb_settings.php");
if ($_REQUEST['download']) {
	$active = false;
} else {
	$active = true;
}

$tab_array[1] = array("Ripristina", $active, "/services_acb.php");

if ($_REQUEST['download']) {
	$tab_array[] = array("Revision", true, "/services_acb.php?download=" . htmlspecialchars($_REQUEST['download']));
}

$tab_array[] = array("Salva adesso", false, "/services_acb_backup.php");

display_top_tabs($tab_array);

$hostnames = get_hostnames();
?>

<div id="loading">
	<i class="fa fa-spinner fa-spin"></i> Loading, please wait...
</div>


<?php if ($_REQUEST['download'] && (!$input_errors)):

$form = new Form(false);

$section = new Form_Section('Backup Details');

$section->addInput(new Form_Input(
	'download',
	'Revision date/time',
	'text',
	$_REQUEST['download']
))->setWidth(7)->setReadOnly();

$section->addInput(new Form_Input(
	'reason',
	'Revision Reason',
	'text',
	$_REQUEST['reason']
))->setWidth(7)->setReadOnly();

$section->addInput(new Form_Input(
	'shasum',
	'SHA256 summary',
	'text',
	$sha256sum
))->setWidth(7)->setReadOnly();

$section->addInput(new Form_Textarea(
	'config_xml',
	'Encrypted config.xml',
	$ds[1]
))->setWidth(7)->setAttribute("rows", "40")->setAttribute("wrap", "off");

$section->addInput(new Form_Textarea(
	'dec_config_xml',
	'Decrypted config.xml',
	$data
))->setWidth(7)->setAttribute("rows", "40")->setAttribute("wrap", "off");

$form->add($section);

print($form);

?>
<a class="btn btn-primary" title="<?=gettext('Restore this revision')?>" href="services_acb.php?newver=<?= urlencode($_REQUEST['download']) ?>" onclick="return confirm('<?=gettext("Sei sicuro di voler ripristinare {$cv['localtime']}?")?>')"><i class="fa fa-undo"></i> Install this revision</a>

<?php else:

$section2 = new Form_Section('Device key');
$group = new Form_Group("Device key");

$group->add(new Form_Input(
	'devkey',
	'Device key',
	'text',
	$userkey
))->setWidth(7)->setHelp("ID utilizzato per identificare questo firewall (derivato dalla chiave pubblica SSH). " .
	"Vedi aiuto qui sotto per maggiori dettagli. %sSi prega di fare una copia sicura di questo valore di ID. %s Se viene perso, anche i vostri backup " .
	" andranno persi!", "<strong>", "</strong>");

$group->add(new Form_Button(
	'upduserkey',
	'Submit',
	null,
	'fa-save'
))->addClass('btn-success btn-xs');

$group->add(new Form_Button(
	'restore',
	'Reset',
	null,
	'fa-refresh'
))->addClass('btn-info btn-xs');

$section2->add($group);
print($section2);

print('<div class="infoblock">');
print_info_box(gettext("La chiave del dispositivo sopra elencata deriva dalla chiave pubblica SSH del firewall. Quando una configurazione viene salvata, viene identificata da questo valore." .
	" Se si sta ripristinando la configurazione di un altro firewall, incollare la chiave Device di quel firewall nel campo Device ID soprastante e fare clic su \"Invia\"." .
	" Questo bypassa temporaneamente l'ID per questa sessione."), 'info', false);
print('</div>');

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Backup Automatico Configurazione")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
		</div>
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed" id="backups">
				<thead>
					<tr>
						<th width="30%"><?=gettext("Data")?></th>
						<th width="60%"><?=gettext("Modifica Configurazione")?></th>
						<th width="10%"><?=gettext("Azioni")?></th>
					</tr>
				</thead>
				<tbody>

			<?php
				$counter = 0;
				foreach ($confvers as $cv):
			?>
					<tr>
						<td><?= $cv['localtime']; ?></td>
						<td><?= $cv['reason']; ?></td>
						<td>
							<a class="fa fa-undo"		title="<?=gettext('Ripristina questa revisione')?>"	href="services_acb.php?hostname=<?=urlencode($hostname)?>&userkey=<?=urlencode($userkey)?>&newver=<?=urlencode($cv['time'])?>"	onclick="return confirm('<?=gettext("Sei sicuro di voler ripristinare {$cv['localtime']}?")?>')"></a>
							<a class="fa fa-download"	title="<?=gettext('Mostra info')?>"	href="services_acb.php?download=<?=urlencode($cv['time'])?>&hostname=<?=urlencode($hostname)?>&userkey=<?=urlencode($userkey)?>&reason=<?=urlencode($cv['reason'])?>"></a>
<?php
		if ($userkey == $origkey) {
?>
							<a class="fa fa-trash"		title="<?=gettext('Cancella config')?>"	href="services_acb.php?hostname=<?=urlencode($hostname)?>&rmver=<?=urlencode($cv['time'])?>"></a>
<?php 	} ?>
						</td>
					</tr>
				<?php	$counter++;
				endforeach;
				if ($counter == 0): ?>
					<tr>
						<td colspan="3" align="center" class="text-danger"><strong>
							<?=gettext("Nessun backup trovato per questo dispositivo.")?>
							</strong>
						</td>
					</tr>
				<?php else: ?>
					<tr>
						<td colspan="3" align="center">
							<br /><?=gettext("Conteggio attuale dei backup ospitati")?> : <?= $counter ?>
						</td>
					</tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php

endif; ?>

</form>

<script type="text/javascript">
//<![CDATA[
events.push(function(){
	$('#loading').hide();

	// On clicking Submit", reload the page but with a POST parameter "userkey" set
	$('#upduserkey').click(function() {
		var $form = $('<form>');
		var newuserkey = $('#devkey').val();

		$form
			.attr("method", "POST")
			.attr("action", '/services_acb.php')
			// The CSRF magic is required because we will be viewing the results of the POST
			.append(
				$("<input>")
					.attr("type", "hidden")
					.attr("name", "__csrf_magic")
					.val(csrfMagicToken)
			)
			.append(
			$("<input>")
				.attr("type", "hidden")
				.attr("name", "userkey")
				.val(newuserkey)
			)
			.appendTo('body')
			.submit();
	});

	$('#restore').click(function() {
		$('#devkey').val("<?=$origkey?>");
	});
});
//]]>
</script>

<?php include("foot.inc"); ?>
