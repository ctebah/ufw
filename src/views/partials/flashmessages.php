<?php
/**
 * @var \Ufw\Helper\Render $this
 */

    if ($this->flashMessenger()->hasInfos()) {
?>
<div class="container fm">
    <div class="row">
        <div class="col-xs-12 col-md-10 col-md-push-1">
            <div class="alert alert-info alert-dismissible" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <?php echo join('<br>', $this->flashMessenger()->getInfos()); ?>
            </div>
        </div>
    </div>
</div>
<?php
	}

	if ($this->flashMessenger()->hasWarnings()) {
?>
<div class="container fm">
    <div class="row">
        <div class="col-xs-12 col-md-10 col-md-push-1">
            <div class="alert alert-warning alert-dismissible" role="alert">
            	<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        	    <?php echo join('<br>', $this->flashMessenger()->getWarnings()); ?>
            </div>
        </div>
    </div>
</div>
<?php
    }

	if ($this->flashMessenger()->hasErrors()) {
?>
<div class="container fm">
    <div class="row">
        <div class="col-xs-12 col-md-10 col-md-push-1">
            <div class="alert alert-danger alert-dismissible" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <?php echo join('<br>', $this->flashMessenger()->getErrors()); ?>
            </div>
        </div>
    </div>
</div>
<?php
	}
