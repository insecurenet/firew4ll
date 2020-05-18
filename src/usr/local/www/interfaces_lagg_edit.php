<?php
/*
 * interfaces_lagg_edit.php
 *
 * part of pense (https://www.pense.org)
 * Copyright (c) 2004-2019 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-interfaces-lagg-edit
##|*NAME=Interfaces: LAGG: Edit
##|*DESCR=Allow access to the 'Interfaces: LAGG: Edit' page.
##|*MATCH=interfaces_lagg_edit.php*
##|-PRIV

require_once("guiconfig.inc");

init_config_arr(array('laggs', 'lagg'));
$a_laggs = &$config['laggs']['lagg'];

$portlist = get_interface_list();
$laggprotos	  = array("none", "lacp", "failover", "loadbalance", "roundrobin");
$laggprotosuc = array(gettext("NONE"), gettext("LACP"), gettext("FAILOVER"), gettext("LOADBALANCE"), gettext("ROUNDROBIN"));

$protohelp =
'<ul>' .
	'<li>' .
		'<strong>' . $laggprotosuc[0] . '</strong><br />' .
		gettext('This protocol is intended to do nothing: it disables any ' .
				'traffic without disabling the lagg interface itself.') .
	'</li>' .
	'<li>' .
		'<strong>' . $laggprotosuc[1] . '</strong><br />' .
		gettext('Supports the IEEE 802.3ad Link Aggregation Control Protocol ' .
				'(LACP) and the Marker Protocol.	LACP will negotiate a set ' .
				'of aggregable links with the peer in to one or more Link ' .
				'Aggregated Groups.  Each LAG is composed of ports of the ' .
				'same speed, set to full-duplex operation.  The traffic will ' .
				'be balanced across the ports in the LAG with the greatest ' .
				'total speed, in most cases there will only be one LAG which ' .
				'contains all ports.	In the event of changes in physical ' .
				'connectivity, Link Aggregation will quickly converge to a ' .
				'new configuration.') .
	'</li>' .
	'<li>' .
		'<strong>' . $laggprotosuc[2] . '</strong><br />' .
		gettext('Sends and receives traffic only through the master port.  If ' .
				'the master port becomes unavailable, the next active port is ' .
				'used.	The first interface added is the master port; any ' .
				'interfaces added after that are used as failover devices.') .
	'</li>' .
	'<li>' .
		'<strong>' . $laggprotosuc[3] . '</strong><br />' .
		gettext('Balances outgoing traffic across the active ports based on ' .
				'hashed protocol header information and accepts incoming ' .
				'traffic from any active port.	 This is a static setup and ' .
				'does not negotiate aggregation with the peer or exchange ' .
				'frames to monitor the link.  The hash includes the Ethernet ' .
				'source and destination address, and, if available, the VLAN ' .
				'tag, and the IP source and destination address.') .
	'</li>' .
	'<li>' .
		'<strong>' . $laggprotosuc[4] . '</strong><br />' .
		gettext('Distributes outgoing traffic using a round-robin scheduler ' .
				'through all active ports and accepts incoming traffic from ' .
				'any active port.') .
	'</li>' .
'</ul>';

$realifchecklist = array();
/* add LAGG interfaces */
if (is_array($config['laggs']['lagg']) && count($config['laggs']['lagg'])) {
	foreach ($config['laggs']['lagg'] as $lagg) {
		unset($portlist[$lagg['laggif']]);
		$laggiflist = explode(",", $lagg['members']);
		foreach ($laggiflist as $tmpif) {
			$realifchecklist[get_real_interface($tmpif)] = $tmpif;
		}
	}
}

$checklist = get_configured_interface_list(true);

foreach ($checklist as $tmpif) {
	$realifchecklist[get_real_interface($tmpif)] = $tmpif;
}

$id = $_REQUEST['id'];

if (isset($id) && $a_laggs[$id]) {
	$pconfig['laggif'] = $a_laggs[$id]['laggif'];
	$pconfig['members'] = $a_laggs[$id]['members'];
	$laggiflist = explode(",", $a_laggs[$id]['members']);
	foreach ($laggiflist as $tmpif) {
		unset($realifchecklist[get_real_interface($tmpif)]);
	}
	$pconfig['proto'] = $a_laggs[$id]['proto'];
	$pconfig['descr'] = $a_laggs[$id]['descr'];
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	if (is_array($_POST['members'])) {
		$pconfig['members'] = implode(',', $_POST['members']);
	}

	/* input validation */
	$reqdfields = explode(" ", "members proto");
	$reqdfieldsn = array(gettext("Member interfaces"), gettext("Lagg protocol"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (is_array($_POST['members'])) {
		foreach ($_POST['members'] as $member) {
			if (!does_interface_exist($member)) {
				$input_errors[] = sprintf(gettext("Interface supplied as member (%s) is invalid"), $member);
			}
		}
	} else if (!does_interface_exist($_POST['members'])) {
		$input_errors[] = gettext("Interface supplied as member is invalid");
	}

	if (!in_array($_POST['proto'], $laggprotos)) {
		$input_errors[] = gettext("Protocol supplied is invalid");
	}

	if (!$input_errors) {
		$lagg = array();
		$lagg['members'] = implode(',', $_POST['members']);
		$lagg['descr'] = $_POST['descr'];
		$lagg['laggif'] = $_POST['laggif'];
		$lagg['proto'] = $_POST['proto'];
		if (isset($id) && $a_laggs[$id]) {
			$lagg['laggif'] = $a_laggs[$id]['laggif'];
		}

		$lagg['laggif'] = interface_lagg_configure($lagg);
		if ($lagg['laggif'] == "" || !stristr($lagg['laggif'], "lagg")) {
			$input_errors[] = gettext("Error occurred creating interface, please retry.");
		} else {
			if (isset($id) && $a_laggs[$id]) {
				$a_laggs[$id] = $lagg;
			} else {
				$a_laggs[] = $lagg;
			}

			write_config();

			$confif = convert_real_interface_to_friendly_interface_name($lagg['laggif']);
			if ($confif != "") {
				interface_configure($confif);
			}

			// reconfigure any VLANs with this lagg as their parent
			if (is_array($config['vlans']['vlan'])) {
				foreach ($config['vlans']['vlan'] as $vlan) {
					if ($vlan['if'] == $lagg['laggif']) {
						interface_vlan_configure($vlan);
						$confif = convert_real_interface_to_friendly_interface_name($vlan['vlanif']);
						if ($confif != "") {
							interface_configure($confif);
						}
					}
				}
			}

			header("Location: interfaces_lagg.php");
			exit;
		}
	}
}

function build_member_list() {
	global $pconfig, $portlist, $realifchecklist;

	$memberlist = array('list' => array(), 'selected' => array());

	foreach ($portlist as $ifn => $ifinfo) {
		if (array_key_exists($ifn, $realifchecklist)) {
			continue;
		}

		$hwaddr = get_interface_vendor_mac($ifn);

		$memberlist['list'][$ifn] = $ifn . ' (' . $ifinfo['mac'] .
		    ($hwaddr != $ifinfo['mac'] ? " | hw: {$hwaddr}" : '') . ')';

		if (in_array($ifn, explode(",", $pconfig['members']))) {
			array_push($memberlist['selected'], $ifn);
		}
	}

	return($memberlist);
}

$pgtitle = array(gettext("Interfaces"), gettext("LAGGs"), gettext("Edit"));
$pglinks = array("", "interfaces_lagg.php", "@self");
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('LAGG Configuration');

$memberslist = build_member_list();

$section->addInput(new Form_Select(
	'members',
	'*Parent Interfaces',
	$memberslist['selected'],
	$memberslist['list'],
	true // Allow multiples
))->setHelp('Choose the members that will be used for the link aggregation.');

$section->addInput(new Form_Select(
	'proto',
	'*LAGG Protocol',
	$pconfig['proto'],
	array_combine($laggprotos, $laggprotosuc)
))->setHelp($protohelp);

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp("Enter a description here for reference only (Not parsed).");

$section->addInput(new Form_Input(
	'laggif',
	null,
	'hidden',
	$pconfig['laggif']
));

if (isset($id) && $a_laggs[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

include("foot.inc");
