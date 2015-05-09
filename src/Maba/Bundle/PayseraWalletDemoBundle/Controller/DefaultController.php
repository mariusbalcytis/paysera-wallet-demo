<?php

namespace Maba\Bundle\PayseraWalletDemoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    public function indexAction(Request $request)
    {
        $session = $this->get('session');
        $configProvider = $this->get('configuration_provider');
        $config = $configProvider->get();
        $walletApi = $this->get('paysera_wallet_api');

        if ($config === null) {
            $relateForm = $this->createFormBuilder()->add('relate', 'submit')->getForm();
            $oauth = $walletApi->oauthConsumer();
            $relateForm->handleRequest($request);
            if ($relateForm->isValid()) {
                return new RedirectResponse($oauth->getAuthorizationUri(array('email')));
            } else {
                $token = $oauth->getOAuthAccessToken();
                if ($token === null) {
                    return $this->render('MabaPayseraWalletDemoBundle:Default:relate.html.twig', array(
                        'form' => $relateForm->createView(),
                    ));
                } else {
                    $client = $walletApi->walletClientWithToken($token);
                    $config = array(
                        'email' => $client->getUser()->getEmail(),
                        'wallet' => $client->getWallet()->getId(),
                    );
                    $configProvider->set($config);
                }
            }
        }
        $client = $walletApi->walletClient();

        if ($session->has('walletId')) {
            $form = $this->createAutoForm();
            $form->handleRequest($request);
            if ($form->isValid()) {
                $client->acceptTransactionUsingAllowance($form->get('key')->getData(), $session->get('walletId'));
            }
        }

        /** @var \Paysera_WalletApi_Entity_Transaction[] $transactions */
        $transactionKeys = $session->get('transactionKeys', array());
        $transactions = array();
        $hasNew = false;
        $hasAllowance = false;
        foreach ($transactionKeys as $i => $key) {
            $transaction = $client->getTransaction($key);
            if ($transaction->isStatusNew()) {
                $hasNew = true;
            } elseif ($transaction->isStatusReserved()) {
                $transaction = $client->confirmTransaction($transaction->getKey());
                if ($transaction->getAllowance() !== null) {
                    $session->set('walletId', $transaction->getWallet());
                }
            }
            if ($transaction->getAllowance() !== null) {
                $hasAllowance = true;
            }

            $transactions[] = $transaction;
        }

        if (!$hasNew) {
            $transaction = $client->createTransaction(
                (new \Paysera_WalletApi_Entity_Transaction())
                    ->setRedirectUri($request->getUri())
                    ->addPayment(
                        (new \Paysera_WalletApi_Entity_Payment())
                            ->setPrice(new \Paysera_WalletApi_Entity_Money('0.01', 'EUR'))
                            ->setDescription('Login 2015')
                            ->setBeneficiary(
                                (new \Paysera_WalletApi_Entity_WalletIdentifier())
                                    ->setId($config['wallet'])
                            )
                    )
            );
            $transactions[] = $transaction;
        }

        if (!$hasAllowance) {
            $transaction = $client->createTransaction(
                (new \Paysera_WalletApi_Entity_Transaction())
                    ->setRedirectUri($request->getUri())
                    ->setAllowance(
                        (new \Paysera_WalletApi_Entity_Allowance())
                            ->setDescription('Login 2015 auto charging')
                            ->setMaxPrice(new \Paysera_WalletApi_Entity_Money('10', 'EUR'))
                            ->setValidUntil(new \DateTime('+7 days'))
                    )
            );
            $transactions[] = $transaction;
        }

        $session->set('transactionKeys', array_map(function($t) {
            return $t->getKey();
        }, $transactions));

        $forms = array();
        if ($session->has('walletId')) {
            foreach ($transactions as $transaction) {
                if ($transaction->isStatusNew()) {
                    $forms[$transaction->getKey()] = $this->createAutoForm(
                        array('key' => $transaction->getKey())
                    )->createView();
                }
            }
        }

        return $this->render('MabaPayseraWalletDemoBundle:Default:index.html.twig', array(
            'transactions' => $transactions,
            'forms' => $forms,
            'config' => $config,
        ));
    }

    protected function createAutoForm($data = array())
    {
        return $this->createFormBuilder($data)->add('auto confirm', 'submit')->add('key', 'hidden')->getForm();
    }
}
