<?php

namespace Maba\Bundle\PayseraWalletDemoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('MabaPayseraWalletDemoBundle:Default:index.html.twig', array('name' => $name));
    }
}
