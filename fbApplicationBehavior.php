<?php

/**
 * 
 *
 * @author shiki
 */
class fbApplicationBehavior extends CBehavior
{
  /**
	 * Attaches the behavior object to the component.
	 * The default implementation will set the {@link owner} property
	 * and attach event handlers as declared in {@link events}.
	 * Make sure you call the parent implementation if you override this method.
	 * @param CComponent $owner the component that this behavior is to be attached to.
	 */
	public function attach($owner)
	{
    parent::attach($owner);

    $owner->attachEventHandler('onBeginRequest', array($this, 'onOwnerBeginRequest'));
	}

  public function onOwnerBeginRequest($event)
  {
    // fix for IE in iframe
    header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
  }
}