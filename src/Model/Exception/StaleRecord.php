<?php
namespace Ufw\Model\Exception;

/**
 * Optimistic lock lost: the UPDATE matched no row because version (or the row) changed after the read.
 */
class StaleRecord extends \RuntimeException
{
}
