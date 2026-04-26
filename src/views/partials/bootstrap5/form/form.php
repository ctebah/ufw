<?php 
/**
 * @var \Ufw\Form\Base $form
 * @var \Ufw\Helper\Render $this
 */
?>

<?php echo $this->html5Tag()->open('form', $form->getAttribs()); ?>
    <?php foreach ($form->getElements() as $element): ?>
        <?php echo $element; ?>
    <?php endforeach; ?>
<?php echo $this->html5Tag()->close(); ?>
