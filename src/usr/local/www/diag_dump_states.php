<?php
/*
 * diag_dump_states.php
 *
 * part of Firew4LL (https://www.firew4ll.com)
 * Copyright (c) 2020 InSecureNet, SRL (ISN)
 * 
 * forked from pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Colin Smith
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
##|*IDENT=page-diagnostics-showstates
##|*NAME=Diagnostics: Show States
##|*DESCR=Allow access to the 'Diagnostics: Show States' page.
##|*MATCH=diag_dump_states.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("interfaces.inc");

function get_ip($addr) {

	$parts = explode(":", $addr);
	if (count($parts) == 2) {
		return (trim($parts[0]));
	} else {
		/* IPv6 */
		$parts = explode("[", $addr);
		if (count($parts) == 2) {
			return (trim($parts[0]));
		}
	}

	return ("");
}

/* handle AJAX operations */
if (isset($_POST['action']) && $_POST['action'] == "remove") {
	if (isset($_POST['srcip']) && isset($_POST['dstip']) && is_ipaddr($_POST['srcip']) && is_ipaddr($_POST['dstip'])) {
		$retval = pfSense_kill_states($_POST['srcip'], $_POST['dstip']);
		echo htmlentities("|{$_POST['srcip']}|{$_POST['dstip']}|0|");
	} else {
		echo gettext("invalid input");
	}
	return;
}

if (isset($_POST['filter']) && isset($_POST['killfilter'])) {
	if (is_ipaddr($_POST['filter'])) {
		$tokill = $_POST['filter'] . "/32";
	} elseif (is_subnet($_POST['filter'])) {
		$tokill = $_POST['filter'];
	} else {
		// Invalid filter
		$tokill = "";
	}
	if (!empty($tokill)) {
		$retval = pfSense_kill_states($tokill);
		$retval = pfSense_kill_states("0.0.0.0/0", $tokill);
	}
}

$pgtitle = array(gettext("Diagnostics"), gettext("States"), gettext("States"));
$pglinks = array("", "@self", "@self");
include("head.inc");
$delmsg = gettext("Are you sure you wish to delete this state?");
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	$('a[data-entry]').on('click', function() {
		var el = $(this);
		var data = $(this).data('entry').split('|');

		if (confirm("<?=$delmsg?>")) {

			$.ajax(
				'/diag_dump_states.php',
				{
					type: 'post',
					data: {
						action: 'remove',
						srcip: data[0],
						dstip: data[1]
					},
					success: function() {
						el.parents('tr').remove();
					},
			});
		}
	});
});
//]]>
</script>

<?php
$tab_array = array();
$tab_array[] = array(gettext("States"), true, "diag_dump_states.php");
if (isset($config['system']['lb_use_sticky'])) {
	$tab_array[] = array(gettext("Source Tracking"), false, "diag_dump_states_sources.php");
}
$tab_array[] = array(gettext("Reset States"), false, "diag_resetstate.php");
display_top_tabs($tab_array);

// Start of tab content
$current_statecount=`pfctl -si | grep "current entries" | awk '{ print $3 }'`;

$form = new Form(false);

$section = new Form_Section('State Filter', 'secfilter', COLLAPSIBLE|SEC_OPEN);

$iflist = get_configured_interface_with_descr();
$iflist['enc0'] = "IPsec";
$iflist['lo0'] = "lo0";
$iflist['all'] = "all";
if (isset($_POST['interface']))
	$ifselect = $_POST['interface'];
else
	$ifselect = "all";

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$ifselect,
	$iflist
));

$section->addInput(new Form_Input(
	'filter',
	'Filter expression',
	'text',
	$_POST['filter'],
	['placeholder' => 'Simple filter such as 192.168, v6, icmp or ESTABLISHED']
));

$filterbtn = new Form_Button(
	'filterbtn',
	'Filter',
	null,
	'fa-filter'
);
$filterbtn->addClass('btn-primary btn-sm');
$section->addInput(new Form_StaticText(
	'',
	$filterbtn
));

if (isset($_POST['filter']) && (is_ipaddr($_POST['filter']) || is_subnet($_POST['filter']))) {
	$killbtn = new Form_Button(
		'killfilter',
		'Kill States',
		null,
		'fa-trash'
	);
	$killbtn->addClass('btn-danger btn-sm');
	$section->addInput(new Form_StaticText(
		'Kill filtered states',
		$killbtn
	))->setHelp('Remove all states to and from the filtered address');
}

$form->add($section);
print $form;
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("States")?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-condensed table-hover sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th><?=gettext("Interface")?></th>
						<th><?=gettext("Protocol")?></th>
						<th><?=gettext("Source (Original Source) -> Destination (Original Destination)")?></th>
						<th><?=gettext("State")?></th>
						<th data-sortable="false"><?=gettext("Packets")?></th>
						<th data-sortable="false"><?=gettext("Bytes")?></th>
						<th data-sortable="false"></th> <!-- For the optional "Remove" button -->
					</tr>
				</thead>
				<tbody>
<?php
	$arr = array();
	/* RuleId filter. */
	if (isset($_REQUEST['ruleid'])) {
		$ids = explode(",", $_REQUEST['ruleid']);
		for ($i = 0; $i < count($ids); $i++) {
			$arr[] = array("ruleid" => intval($ids[$i]));
		}
	}

	/* Interface filter. */
	if (isset($_POST['interface']) && $_POST['interface'] != "all") {
		$arr[] = array("interface" => get_real_interface($_POST['interface']));
	}

	if (isset($_POST['filter']) && strlen($_POST['filter']) > 0) {
		$arr[] = array("filter" => $_POST['filter']);
	}

	if (isset($_POST['filter']) || !isset($config['system']['webgui']['requirestatefilter'])) {
		if (count($arr) > 0) {
			$res = pfSense_get_pf_states($arr);
		} else {
			$res = pfSense_get_pf_states();
		}
	} else {
		$res = NULL;
	}

	$states = 0;
	if ($res != NULL && is_array($res)) {
		$states = count($res);
	}

	for ($i = 0; $i < $states; $i++):
		$info = $res[$i]['src'];
		$srcip = get_ip($res[$i]['src']);
		$dstip = get_ip($res[$i]['dst']);
		if ($res[$i]['src-orig']) {
			$info .= " (" . $res[$i]['src-orig'] . ")";
		}
		$info .= " -> ";
		$info .= $res[$i]['dst'];
		if ($res[$i]['dst-orig']) {
			$info .= " (" . $res[$i]['dst-orig'] . ")";
			$killdstip = get_ip($res[$i]['dst-orig']);
		} else {
			$killdstip = $dstip;
		}

?>
					<tr>
						<td><?= convert_real_interface_to_friendly_descr($res[$i]['if']) ?></td>
						<td><?= $res[$i]['proto'] ?></td>
						<td><?= $info ?></td>
						<td><?= $res[$i]['state'] ?></td>
						<td><?= format_number($res[$i]['packets in']) ?> /
						    <?= format_number($res[$i]['packets out']) ?></td>
						<td><?= format_bytes($res[$i]['bytes in']) ?> /
						    <?= format_bytes($res[$i]['bytes out']) ?></td>

						<td>
							<a class="btn fa fa-trash no-confirm" data-entry="<?=$srcip?>|<?=$killdstip?>"
								title="<?=sprintf(gettext('Remove all state entries from %1$s to %2$s'), $srcip, $killdstip);?>"></a>
						</td>
					</tr>
<?php
	endfor;
?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php

if ($states == 0) {
	if (isset($_POST['filter']) && !empty($_POST['filter'])) {
		$errmsg = gettext('No states were found that match the current filter.');
	} else if (!isset($_POST['filter']) && isset($config['system']['webgui']['requirestatefilter'])) {
		$errmsg = gettext('State display suppressed without filter submission. '.
		'See System > General Setup, Require State Filter.');
	} else {
		$errmsg = gettext('No states were found.');
	}

	print_info_box($errmsg, 'warning', false);
}

include("foot.inc");
