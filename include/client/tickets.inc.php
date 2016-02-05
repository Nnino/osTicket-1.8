<?php
if(!defined('OSTCLIENTINC') || !is_object($thisclient) || !$thisclient->isValid()) die('Access Denied');
$qs = array();
$status=null;
if(isset($_REQUEST['status'])) { //Query string status has nothing to do with the real status used below.
    $qs += array('status' => $_REQUEST['status']);
    //Status we are actually going to use on the query...making sure it is clean!
    $status=strtolower($_REQUEST['status']);
    switch(strtolower($_REQUEST['status'])) {
     case 'open':
		$results_type=__('Open Tickets');
     case 'closed':
		$results_type=__('Closed Tickets');
        break;
     case 'resolved':
        $results_type=__('Resolved Tickets');
        break;
     default:
        $status=''; //ignore
    }
} elseif($thisclient->getNumOpenTickets()) {
    $status='open'; //Defaulting to open
	$results_type=__('Open Tickets');
}

$sortOptions=array('id'=>'`number`', 'subject'=>'cdata.subject',
                    'status'=>'status.name', 'dept'=>'dept_name','date'=>'ticket.created');
$orderWays=array('DESC'=>'DESC','ASC'=>'ASC');
//Sorting options...
$order_by=$order=null;
$sort=($_REQUEST['sort'] && $sortOptions[strtolower($_REQUEST['sort'])])?strtolower($_REQUEST['sort']):'date';
if($sort && $sortOptions[$sort])
    $order_by =$sortOptions[$sort];

$order_by=$order_by?$order_by:'ticket_created';
if($_REQUEST['order'] && $orderWays[strtoupper($_REQUEST['order'])])
    $order=$orderWays[strtoupper($_REQUEST['order'])];

$order=$order?$order:'ASC';
if($order_by && strpos($order_by,','))
    $order_by=str_replace(','," $order,",$order_by);

$x=$sort.'_sort';
$$x=' class="'.strtolower($order).'" ';

$qselect='SELECT ticket.ticket_id,ticket.`number`,ticket.dept_id,isanswered, '
    .'dept.ispublic, cdata.subject,'
    .'IF(ptopic.topic_pid IS NULL, topic.topic, CONCAT_WS(" / ", ptopic.topic, topic.topic)) as helptopic,'
    .'dept_name, status.name as status, status.state, ticket.source, ticket.created,sla.name as sla_name, cdata.proyecto as cdata_project';

$qfrom='FROM '.TICKET_TABLE.' ticket '
      .' LEFT JOIN '.TICKET_STATUS_TABLE.' status
            ON (status.id = ticket.status_id) '
      .' LEFT JOIN '.TABLE_PREFIX.'ticket__cdata cdata ON (cdata.ticket_id = ticket.ticket_id)'
      .' LEFT JOIN '.DEPT_TABLE.' dept ON (ticket.dept_id=dept.dept_id) '
      .' LEFT JOIN '.SLA_TABLE.' sla ON (ticket.sla_id=sla.id AND sla.isactive=1) '
      .' LEFT JOIN '.TOPIC_TABLE.' topic ON (ticket.topic_id=topic.topic_id) '
      .' LEFT JOIN '.TOPIC_TABLE.' ptopic ON (ptopic.topic_id=topic.topic_pid) '
      .' LEFT JOIN '.TICKET_COLLABORATOR_TABLE.' collab
        ON (collab.ticket_id = ticket.ticket_id
                AND collab.user_id ='.$thisclient->getId().' )';

$qwhere = sprintf(' WHERE ( ticket.user_id=%d OR collab.user_id=%d )',
            $thisclient->getId(), $thisclient->getId());

$states = array(
        'open' => 'open',
        'closed' => 'closed');
if($status && isset($states[$status])){
    $qwhere.=' AND status.state='.db_input($states[$status]);
}

$search=($_REQUEST['a']=='search' && $_REQUEST['q']);
if($search) {
    $qs += array('a' => $_REQUEST['a'], 'q' => $_REQUEST['q']);
    $queryterm=db_real_escape($_REQUEST['q'],false); //escape the term ONLY...no quotes.
    if(is_numeric($_REQUEST['q'])) {
        $qwhere.=" AND ticket.`number` LIKE '$queryterm%'";
    } else {//Deep search!
        $qwhere.=' AND ( '
                ." cdata.subject LIKE '%$queryterm%'"
                ." OR thread.body LIKE '%$queryterm%'"
                .' ) ';
        $deep_search=true;
        //Joins needed for search
        $qfrom.=' LEFT JOIN '.TICKET_THREAD_TABLE.' thread ON ('
               .'ticket.ticket_id=thread.ticket_id AND thread.thread_type IN ("M","R"))';
    }
}

TicketForm::ensureDynamicDataView();

$total=db_count('SELECT count(DISTINCT ticket.ticket_id) '.$qfrom.' '.$qwhere);
$page=($_GET['p'] && is_numeric($_GET['p']))?$_GET['p']:1;
$pageNav=new Pagenate($total, $page, PAGE_LIMIT);
$qstr = '&amp;'. Http::build_query($qs);
$qs += array('sort' => $_REQUEST['sort'], 'order' => $_REQUEST['order']);
$pageNav->setURL('tickets.php', $qs);

//more stuff...
$qselect.=' ,count(attach_id) as attachments ';
$qfrom.=' LEFT JOIN '.TICKET_ATTACHMENT_TABLE.' attach ON  ticket.ticket_id=attach.ticket_id ';
$qgroup=' GROUP BY ticket.ticket_id';

$query="$qselect $qfrom $qwhere $qgroup ORDER BY $order_by $order LIMIT ".$pageNav->getStart().",".$pageNav->getLimit();
//echo $query;
$res = db_query($query);
$showing=($res && db_num_rows($res))?$pageNav->showing():"";
if(!$results_type)
{
	$results_type=ucfirst($status).' Tickets';
}
$showing.=($status)?(' '.$results_type):' '.__('All Tickets');
if($search)
    $showing=__('Search Results').": $showing";

$negorder=$order=='DESC'?'ASC':'DESC'; //Negate the sorting

?>
<h1><?php echo __('Tickets');?></h1>
<br>
<form action="tickets.php" method="get" id="ticketSearchForm">
    <input type="hidden" name="a"  value="search">
    <input type="text" name="q" size="20" value="<?php echo Format::htmlchars($_REQUEST['q']); ?>">
    <select name="status">
        <option value="">&mdash; <?php echo __('Any Status');?> &mdash;</option>
        <option value="open"
            <?php echo ($status=='open') ? 'selected="selected"' : '';?>>
            <?php echo _P('ticket-status', 'Open');?> (<?php echo $thisclient->getNumOpenTickets(); ?>)</option>
        <?php
        if($thisclient->getNumClosedTickets()) {
            ?>
        <option value="closed"
            <?php echo ($status=='closed') ? 'selected="selected"' : '';?>>
            <?php echo __('Closed');?> (<?php echo $thisclient->getNumClosedTickets(); ?>)</option>
        <?php
        } ?>
    </select>
    <input type="submit" value="<?php echo __('Go');?>">
</form>
<a class="refresh" href="<?php echo Format::htmlchars($_SERVER['REQUEST_URI']); ?>"><?php echo __('Refresh'); ?></a>
<table id="ticketTable" width="800" border="0" cellspacing="0" cellpadding="0">
    <caption><?php echo $showing; ?></caption>
    <thead>
        <tr>
            <th nowrap>
                <a href="tickets.php?sort=ID&order=<?php echo $negorder; ?><?php echo $qstr; ?>" title="Sort By Ticket ID"><?php echo __('Ticket #');?></a>
            </th>
            <th width="120">
                <a href="tickets.php?sort=date&order=<?php echo $negorder; ?><?php echo $qstr; ?>" title="Sort By Date"><?php echo __('Create Date');?></a>
            </th>
            <th width="100">
                <a href="tickets.php?sort=status&order=<?php echo $negorder; ?><?php echo $qstr; ?>" title="Sort By Status"><?php echo __('Status');?></a>
            </th>
            <th width="320">
                <a href="tickets.php?sort=subj&order=<?php echo $negorder; ?><?php echo $qstr; ?>" title="Sort By Subject"><?php echo __('Subject');?></a>
            </th>
            <th width="320">
                <a href="tickets.php?sort=subj&order=<?php echo $negorder; ?><?php echo $qstr; ?>" title="Sort By Help Topic"><?php echo __('Help Topic');?></a>
            </th>
            <th width="320">
                <a href="tickets.php?sort=subj&order=<?php echo $negorder; ?><?php echo $qstr; ?>" title="Sort By SLA Plan"><?php echo __('SLA Plan');?></a>
            </th>
            <th width="320">
                <a href="tickets.php?sort=subj&order=<?php echo $negorder; ?><?php echo $qstr; ?>" title="Sort By Project"><?php echo __('Proyecto');?></a>
            </th>
        </tr>
    </thead>
    <tbody>
    <?php
     $subject_field = TicketForm::objects()->one()->getField('subject');
     if($res && ($num=db_num_rows($res))) {
        $defaultDept=Dept::getDefaultDeptName(); //Default public dept.
        $query_proyecto = 'SELECT litems.value as litems_nombre, litems.id as litems_id FROM ost_list_items litems WHERE litems.list_id=2';
        $res2 = db_query($query_proyecto);
        $nombres = array();
        while($resultado = db_fetch_array($res2)){
            $nombres[$resultado['litems_id']] = $resultado['litems_nombre'];
        }
        while ($row = db_fetch_array($res)) {
            $var_row = preg_split("/,/",$row['cdata_project']);
            $row['cdata_project'] = $nombres[$var_row[0]];   

            $dept= $row['ispublic']? $row['dept_name'] : $defaultDept;
            $subject = Format::truncate($subject_field->display(
                $subject_field->to_php($row['subject']) ?: $row['subject']
            ), 40);
            if($row['attachments'])
                $subject.='  &nbsp;&nbsp;<span class="Icon file"></span>';

            $ticketNumber=$row['number'];
            if($row['isanswered'] && !strcasecmp($row['state'], 'open')) {
                $subject="<b>$subject</b>";
                $ticketNumber="<b>$ticketNumber</b>";
            }
            ?>
            <tr id="<?php echo $row['ticket_id']; ?>">
                <td>
                <a class="Icon <?php echo strtolower($row['source']); ?>Ticket" title="<?php echo $row['email']; ?>"
                    href="tickets.php?id=<?php echo $row['ticket_id']; ?>"><?php echo $ticketNumber; ?></a>
                </td>
                <td>&nbsp;<?php echo Format::db_date($row['created']); ?></td>
                <td>&nbsp;<?php echo $row['status']; ?></td>
                <td>
                    <a href="tickets.php?id=<?php echo $row['ticket_id']; ?>"><?php echo $subject; ?></a>
                </td>
                 <td>
                    <?php echo $row['helptopic']; ?>
                </td>
                <td>
                    <?php echo $row['sla_name']; ?>
                </td>
                <td>
                    <?php echo $row['cdata_project']; ?>
                </td>
            </tr>
        <?php
        }

     } else {
         echo '<tr><td colspan="6">'.__('Your query did not match any records').'</td></tr>';
     }
    ?>
    </tbody>
</table>
<?php
if($res && $num>0) {
    echo '<div>&nbsp;'.__('Page').':'.$pageNav->getPageLinks().'&nbsp;</div>';
}

?>

<hr/>
<h2><?php echo __('Statistics'); ?>&nbsp;</h2>
<br>
    <?php 
    $qselect='SELECT ticket.ticket_id,ticket.`number`,ticket.topic_id, ticket.status_id, ticket.isoverdue, ticket.reopened,'
    .'IF(ptopic.topic_pid IS NULL, topic.topic, CONCAT_WS(" / ", ptopic.topic, topic.topic)) as helptopic,'
    .'status.name as status, status.state, cdata.proyecto as cdata_project FROM ost_ticket ticket '
      .' LEFT JOIN ost_ticket_status status
            ON (status.id = ticket.status_id) '
      .' LEFT JOIN '.TABLE_PREFIX.'ticket__cdata cdata ON (cdata.ticket_id = ticket.ticket_id)'
      .' LEFT JOIN '.TOPIC_TABLE.' topic ON (ticket.topic_id=topic.topic_id) '
      .' LEFT JOIN '.TOPIC_TABLE.' ptopic ON (ptopic.topic_id=topic.topic_pid)';


    $resp3 = db_query($qselect);

    $contadorProyectos = array();
    $contadorEstados = array();

    $arrayTickets = array();
    while($result = db_fetch_array($resp3)){
        $arrayTickets[$result['ticket_id']] = $result;
    }

    foreach ($arrayTickets as $result) {
        $var_row = preg_split("/,/",$result['cdata_project']);
        $result['cdata_project'] = $nombres[$var_row[0]];   
        if(!array_key_exists($result["helptopic"], $contadorEstados)){
            $contadorEstados[$result["helptopic"]]["abiertos"]=0;
            $contadorEstados[$result["helptopic"]]["cerrados"]=0;
            $contadorEstados[$result["helptopic"]]["vencidos"]=0;
            $contadorEstados[$result["helptopic"]]["reabiertos"]=0;
        }
        if($result["isoverdue"]=="1"){
        //    echo $result["ticket_id"];
        //    echo "<br>";
            $contadorEstados[$result["helptopic"]]["vencidos"]++;
        }
        if ($result["reopened"]){
            $contadorEstados[$result["helptopic"]]["reabiertos"]++;
        }
        if ($result["state"]=="open"){
            $contadorEstados[$result["helptopic"]]["abiertos"]++;
        }
        if ($result["state"]=="closed"){
            $contadorEstados[$result["helptopic"]]["cerrados"]++;
        }

        if(!array_key_exists($result["cdata_project"], $contadorProyectos)){
            $contadorProyectos[$result["cdata_project"]]["incidenteCritico"]=0;
            $contadorProyectos[$result["cdata_project"]]["mejora"]=0;
            $contadorProyectos[$result["cdata_project"]]["incidenteBaja"]=0;
            $contadorProyectos[$result["cdata_project"]]["incidenteMedia"]=0;
            $contadorProyectos[$result["cdata_project"]]["incidente"]=0;
            $contadorProyectos[$result["cdata_project"]]["incidenteAlta"]=0;
        }
        if($result["helptopic"]=="Incidente / Prioridad Alta"){
            $contadorProyectos[$result["cdata_project"]]["incidenteAlta"]++;
        } else if($result["helptopic"]=="Incidente"){
            $contadorProyectos[$result["cdata_project"]]["incidente"]++;
        } else if($result["helptopic"]=="Incidente / Prioridad Media"){
            $contadorProyectos[$result["cdata_project"]]["incidenteMedia"]++;
        } else if($result["helptopic"]=="Incidente / Prioridad Baja"){
            $contadorProyectos[$result["cdata_project"]]["incidenteBaja"]++;
        } else if($result["helptopic"]=="Incidente / Crítico"){
            $contadorProyectos[$result["cdata_project"]]["incidenteCritico"]++;
        } else if($result["helptopic"]=="Mejora"){
            $contadorProyectos[$result["cdata_project"]]["mejora"]++;
        }
    }
    

    ?>
<ul class="nav nav-tabs">
    <li class="active"><a data-toggle="tab" href="#tablaTickets">Estado Tickets</a></li>
    <li><a data-toggle="tab" href="#tablaProyectos">Proyectos</a></li>
</ul>
<div class="tab-content">
    <div id="tablaTickets" class="tab-pane fade in active">
        <p>
            <table class="table table-condensed table-striped" width="800" border="0" cellspacing="0" cellpadding="0">
                    <thead  nowrap>
                        <tr>
                            <th>Temas de Ayuda</th>
                            <th>Abiertos</th>
                            <th>Vencidos</th>
                            <th>Cerrados</th>
                            <th>Reabiertos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($contadorEstados as $claveEstado => $contEstado){ ?>
                        <tr>
                            <th><?php echo $claveEstado; ?></th>
                            <td><?php echo $contadorEstados[$claveEstado]["abiertos"]; ?></td>
                            <td><?php echo $contadorEstados[$claveEstado]["vencidos"]; ?></td>
                            <td><?php echo $contadorEstados[$claveEstado]["cerrados"]; ?></td>
                            <td><?php echo $contadorEstados[$claveEstado]["reabiertos"]; ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
            </table>
        </p>
    </div>
    <div id="tablaProyectos" class="tab-pane fade">
        <p>
            <table class="table table-condensed table-striped" width="800" border="0" cellspacing="0" cellpadding="0">
                <thead  nowrap>
                    <tr>
                        <th>Proyecto</th>
                        <th>Incidente</th>
                        <th>Incidente Prioridad Baja</th>
                        <th>Incidente Prioridad Media</th>
                        <th>Incidente Prioridad Alta</th>
                        <th>Incidente Crítico</th>
                        <th>Mejora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($contadorProyectos as $claveProyecto => $contProyecto){ ?>
                    <tr>
                        <th><?php echo $claveProyecto; ?></th>
                        <td><?php echo $contadorProyectos[$claveProyecto]["incidente"]; ?></td>
                        <td><?php echo $contadorProyectos[$claveProyecto]["incidenteBaja"]; ?></td>
                        <td><?php echo $contadorProyectos[$claveProyecto]["incidenteMedia"]; ?></td>
                        <td><?php echo $contadorProyectos[$claveProyecto]["incidenteAlta"]; ?></td>
                        <td><?php echo $contadorProyectos[$claveProyecto]["incidenteCritico"]; ?></td>
                        <td><?php echo $contadorProyectos[$claveProyecto]["mejora"]; ?></td>
                    </tr>
                    <?php } ?>
                    
                </tbody>
            </table>
        </p>
    </div>
</div>

