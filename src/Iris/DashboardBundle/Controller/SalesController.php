<?php

namespace Iris\DashboardBundle\Controller;

use PDO;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

//include 'ChromePhp.php';
//use ChromePhp;

// Flash Messages Styles
//success (green)
//info (blue)
//warning (yellow)
//danger (red)

class SalesController extends Controller
{

    public function manageBundleAction()
    {
        $context = $this->container->get('security.context');
        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');
        //auth

        $conn = $this->get_Store_DB_Object();
        $stmt = $conn->prepare('SELECT * FROM Bundle');
        $stmt->execute();
        $bundle = $stmt->fetchAll();

        return $this->render('DashboardBundle:Sales:manage-bundle.html.twig', array(
            'bundles' => $bundle));
    }

    public function get_Store_DB_Object()
    {
        $dbname = $this->container->getParameter('store_database_name');
        $username = $this->container->getParameter('store_database_user');
        $password = $this->container->getParameter('store_database_password');
        $servername = $this->container->getParameter('store_database_host');

        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    }

    public function newBundleAction()
    {
        $context = $this->container->get('security.context');
        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');
        //auth
        $request = $this->get('request');

        if ($request->getMethod() == 'POST') {
            //first get the value of post variables
            $bundle_name = $request->request->get('bundle-name');
            $bundle_price = $request->request->get('bundle-price');
            $bundle_description = $request->request->get('bundle-description');
            $app_identifiers = $request->request->get('app-identifiers');//string of application id's separated with ','
            $app_identifiers = explode(",", $app_identifiers);
            // connect to database
            $conn = $this->get_Store_DB_Object();
            //insert the new bundle
            $stmt = $conn->prepare('INSERT INTO Bundle (Name,Description,Price) VALUES (?,?,?)');
            try {
                $stmt->execute([$bundle_name, $bundle_description, $bundle_price]);
            } catch (\PDOException $e) {
                $error = 'Operation Aborted ..' . $e->getMessage();
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            }
            // get  ID for the new bundle
            $stmt = $conn->prepare('select ID from Bundle where Name=?');
            $stmt->execute([$bundle_name]);
            $bundle_id = $stmt->fetchAll()[0]['ID'];
            $stmt = $conn->prepare('INSERT INTO BundleApplication (BundleID,ApplicationID) VALUES (?,?)');
            //save the list of applications inside bundle
            for ($i = 0; $i < count($app_identifiers); $i++) {
                $stmt->execute([$bundle_id, $app_identifiers[$i]]);
            }
            return $this->render('DashboardBundle:Sales:new-update-delete-bundle-result.html.twig');
        }
        $conn = $this->get_Store_DB_Object();
        $stmt = $conn->prepare('SELECT * FROM Application');
        try {
            $stmt->execute();
            $applications = $stmt->fetchAll();
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        return $this->render('DashboardBundle:Sales:new-update-bundle.html.twig', array('update' => false, 'apps' => $applications));
    }

    public function editBundleAction($slug)
    {

        $context = $this->container->get('security.context');

        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');

        //auth
        $request = $this->get('request');

        if ($request->getMethod() == 'POST') {
            return $this->updateBundle($request);
        }
        //post method not used default page here
        $bundle_id = $slug;
        $conn = $this->get_Store_DB_Object();
        $stmt = $conn->prepare('SELECT  * FROM Application
                                WHERE ID  in (SELECT ApplicationID FROM BundleApplication
                                WHERE BundleID=?);');
        try {
            $stmt->execute([$bundle_id]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $apps_of_Bundle = $stmt->fetchAll();
        //get bundle info
        $stmt = $conn->prepare('SELECT * FROM Bundle where ID=?');
        try {
            $stmt->execute([$bundle_id]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $bundle = $stmt->fetchAll()[0];
        //get application that are not inside bundle
        $stmt = $conn->prepare('SELECT  * FROM Application
                                WHERE ID NOT in (SELECT ApplicationID FROM BundleApplication
                                WHERE BundleID=?);');
        try {
            $stmt->execute([$bundle_id]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $applications = $stmt->fetchAll();
        return $this->render('DashboardBundle:Sales:new-update-bundle.html.twig', array('update' => true, 'apps' => $applications, 'bundle' => $bundle, 'appsOfBundle' => $apps_of_Bundle));

    }

    public function updateBundle($request)
    {
        //first get the value of post variables
        $bundle_name = $request->request->get('bundle-name');
        $bundle_price = $request->request->get('bundle-price');
        $bundle_description = $request->request->get('bundle-description');
        $app_identifiers = $request->request->get('app-identifiers');//string of application id's separated with ','
        $app_identifiers = explode(",", $app_identifiers);
        // connect to database
        $conn = $this->get_Store_DB_Object();
        // get  ID for bundle
        $stmt = $conn->prepare('select ID from Bundle where Name=?');
        $stmt->execute([$bundle_name]);
        $bundle_id = $stmt->fetchAll()[0]['ID'];
        //update description
        $stmt = $conn->prepare('update Bundle set Description=?,Price=? where ID=?');
        try {
            $stmt->execute([$bundle_description, $bundle_price, $bundle_id]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        //delete all delete all bundle applications
        $stmt = $conn->prepare('select distinct UserID from BundlesTracking where BundleID=?');
        try {
            $stmt->execute([$bundle_id]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $users = $stmt->fetchAll();
        $stmt = $conn->prepare('select distinct ApplicationID from BundlesTracking where BundleID=?');
        try {
            $stmt->execute([$bundle_id]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $applications_to_remove = $stmt->fetchAll();
        $stmt = $conn->prepare('delete from BundleApplication  where BundleID=?');
        $stmt1 = $conn->prepare('delete from BundlesTracking  where BundleID=?');
        try {
            $stmt->execute([$bundle_id]);
            $stmt1->execute([$bundle_id]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        //insert application list to bundle
        $stmt = $conn->prepare('INSERT INTO BundleApplication (BundleID,ApplicationID) VALUES (?,?)');
        try {
            for ($i = 0; $i < count($app_identifiers); $i++) {
                $stmt->execute([$bundle_id, $app_identifiers[$i]]);
                foreach ($users as $user) {
                    try {

                        $stmt1 = $conn->prepare('INSERT INTO BundlesTracking (UserID, ApplicationID, BundleID) VALUES (?,?,?)');
                        $uu = $user['UserID'];
                        $stmt1->execute([$uu, $app_identifiers[$i], $bundle_id]);
                        $stmt1 = $conn->prepare('Select Type from Application where ID=?');
                        $stmt1->execute([$app_identifiers[$i]]);
                        if ($stmt1->fetch()['Type'] == 'dongle') {
                            $stmt1 = $conn->prepare('INSERT INTO DongleInstallation (ClientID, ApplicationID, Version, Status,Subscription) VALUES (?,?,(select max(Version) from Version where ApplicationID=?),(select PK from Status where Status.status="none"),true)');
                            $stmt1->execute([$user['UserID'], $app_identifiers[$i], $app_identifiers[$i]]);
                        } else {
                            $stmt1 = $conn->prepare('INSERT INTO ControllerInstallation (ClientID, ApplicationID, Version, Status,Subscription) VALUES (?,?,(select max(Version) from Version where ApplicationID=?),(select PK from Status where Status.status="none"),true)');
                            $stmt1->execute([$user['UserID'], $app_identifiers[$i], $app_identifiers[$i]]);
                        }
                    } catch (\PDOException $e) {

                    }
                    try {
                        foreach ($applications_to_remove as $app) {
                            $stmt1 = $conn->prepare('Select Type from Application where ID=?');
                            $stmt1->execute([$app['ApplicationID']]);
                            if ($stmt1->fetch()['Type'] == 'dongle') {
                                $stmt1 = $conn->prepare('delete from DongleInstallation where Subscription=true and ApplicationID=? and ClientID=? and (select  count(distinct BundleID)from BundlesTracking where BundlesTracking.ApplicationID=? and UserID=?) < 1 ');
                                $stmt1->execute([$app['ApplicationID'], $user['UserID'], $app['ApplicationID'], $user['UserID']]);
                            } else {
                                $stmt1 = $conn->prepare('delete from ControllerInstallation where Subscription=true and ApplicationID=? and ClientID=? and (select  count(distinct BundleID)from BundlesTracking where BundlesTracking.ApplicationID=? and UserID=?) < 1 ');
                                $stmt1->execute([$app['ApplicationID'], $user['UserID'], $app['ApplicationID'], $user['UserID']]);
                            }
                        }
                    } catch (\PDOException $e) {

                    }

                }
            }

//            $stmt1 = $conn->prepare('delete from DongleInstallation where Subscription=true and ApplicationID=? and (select  count(distinct BundleID)from BundlesTracking where BundlesTracking.ApplicationID=?) <= 1 ');
//            $stmt1->execute([]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        return $this->render('DashboardBundle:Sales:new-update-delete-bundle-result.html.twig');

    }

    public function deleteBundleAction($slug)
    {
        $context = $this->container->get('security.context');

        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');
        //auth

        //connect to database
        $bundle_identifier = $slug;
        $request = $this->get('request');
        $conn = $this->get_Store_DB_Object();
        //delete bundle
        $stmt = $conn->prepare('DELETE FROM Bundle WHERE ID = ?');
        try {
            $stmt->execute([$bundle_identifier]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        //delete application list of bundle
        $this->deleleteApplicationBundle($bundle_identifier);
        $stmt = $conn->prepare('DELETE FROM BundleApplication WHERE BundleID = ?');
        try {
            $stmt->execute([$bundle_identifier]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        //dlete subscriptions for the bundle
        $stmt = $conn->prepare('DELETE FROM Subscription WHERE BundleID = ?');
        try {
            $stmt->execute([$bundle_identifier]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        return $this->render('DashboardBundle:Sales:new-update-delete-bundle-result.html.twig');

    }

    public function deleleteApplicationBundle($deletedbundleID)
    {
        $conn = $this->get_Store_DB_Object();
        $stmt = $conn->prepare('select * from BundleApplication,Application where BundleID=? and ApplicationID=Application.ID ');
        $stmt->execute([$deletedbundleID]);
        While ($app = $stmt->fetch()) {
            if ($app['Type'] == 'dongle') {
                $stmt1 = $conn->prepare('delete from DongleInstallation where Subscription=true and ApplicationID=? and (select  count(*)from BundlesTracking where BundlesTracking.ApplicationID=? ) <= 1 ');
                $stmt1->execute([$app['ApplicationID'], $app['ApplicationID']]);
            } else {
                $stmt1 = $conn->prepare('delete from ControllerInstallation where Subscription=true and ApplicationID=? and (select  count(*)from BundlesTracking where BundlesTracking.ApplicationID=? )<=1 ');
                $stmt1->execute([$app['ApplicationID'], $app['ApplicationID']]);
            }
            $stmt1 = $conn->prepare('delete from BundlesTracking where ApplicationID=? and BundleID=?');
            $stmt1->execute([$app['ApplicationID'], $deletedbundleID]);
        }
    }

    public function manageApplicationsAction()
    {
        $context = $this->container->get('security.context');

        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');
        $request = $this->get('request');
        //auth

        //connect to database
        $conn = $this->get_Store_DB_Object();
        //get applications with number of clients
        $stmt = $conn->prepare('Select *,(select count(*)  from Client,Purchase where Client.ID=Purchase.ClientID and Purchase.ApplicationID=Application.ID) As NumberOfClients from Application order by Application.ID ');
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $applications = $stmt->fetchAll();
        return $this->render('DashboardBundle:Sales:manage-application.html.twig', array(
            'applications' => $applications));
    }

    public function showApplicationClientsAction($slug)
    {
        $context = $this->container->get('security.context');

        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');

        //auth
        $request = $this->get('request');
        $applicationID = $slug;
        //connect to database
        $conn = $this->get_Store_DB_Object();
        //get application clients
        $stmt = $conn->prepare('Select *,'
            . '(select WebDownloadDate from ControllerInstallation as cont '
            . 'where clientID=Purchase.ClientID  and ApplicationID=Purchase.ApplicationID and WebDownloadDate '
            . 'in(select max(cont.WebDownloadDate) as m from ControllerInstallation as cont '
            . 'where clientID=Purchase.ClientID  and ApplicationID=Purchase.ApplicationID )'
            . ') as WebDownloadDate,'
            . '(select InstallationDate from ControllerInstallation as cont '
            . 'where clientID=Purchase.ClientID  and ApplicationID=Purchase.ApplicationID and InstallationDate '
            . 'in(select max(cont.InstallationDate) as m from ControllerInstallation as cont '
            . 'where clientID=Purchase.ClientID  and ApplicationID=Purchase.ApplicationID )'
            . ') as InstallationDate,'
            . '(select Version from ControllerInstallation as cont '
            . 'where clientID=Purchase.ClientID  and ApplicationID=Purchase.ApplicationID and InstallationDate '
            . 'in(select max(cont.InstallationDate) as m from ControllerInstallation as cont '
            . 'where clientID=Purchase.ClientID  and ApplicationID=Purchase.ApplicationID )'
            . ') as InstalledVersion,'
            . '(select Version from ControllerInstallation as cont '
            . 'where clientID=Purchase.ClientID  and ApplicationID=Purchase.ApplicationID and WebDownloadDate '
            . 'in(select max(cont.WebDownloadDate) as m from ControllerInstallation as cont '
            . 'where clientID=Purchase.ClientID  and ApplicationID=Purchase.ApplicationID )) as DownlodedVersion'
            . ' from Purchase,Client '
            . 'where ApplicationID=? and Client.ID=ClientID');
        $stmt2 = $conn->prepare('Select Name from Application where ID=?');
        try {
            $stmt->execute([$applicationID]);
            $stmt2->execute([$applicationID]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }

        $clients = $stmt->fetchAll();
        $applicationname = $stmt2->fetchAll()[0];
        return $this->render('DashboardBundle:Sales:show-application-clients.html.twig', array(
            'clients' => $clients, 'applicationname' => $applicationname));

    }

    public function manageBundlesAction()
    {
        $context = $this->container->get('security.context');

        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');

        //auth
        $request = $this->get('request');
        //connect to database
        $conn = $this->get_Store_DB_Object();
        //get Bundles with number of clients
        $stmt = $conn->prepare('Select *,(select count(*)  from Client,Subscription where Client.ID=Subscription.ClientID and Subscription.BundleID=Bundle.ID) As NumberOfClients from Bundle order by Bundle.ID');
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $Bundles = $stmt->fetchAll();
        return $this->render('DashboardBundle:Sales:manage-bundles.html.twig', array(
            'bundles' => $Bundles));
    }

    public function showBundleClientsAction($slug)
    {
        $context = $this->container->get('security.context');

        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');

        //auth
        $request = $this->get('request');
        $BundleID = $slug;
        //connect to database
        $conn = $this->get_Store_DB_Object();
        //get Bundle  clients
        $stmt = $conn->prepare('Select * from Subscription,Client where BundleID=? and Client.ID=ClientID');
        $stmt2 = $conn->prepare('Select Name from Bundle where ID=?');
        try {
            $stmt->execute([$BundleID]);
            $stmt2->execute([$BundleID]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $clients = $stmt->fetchAll();
        $BundleName = $stmt2->fetchAll()[0];
        return $this->render('DashboardBundle:Sales:show-bundle-clients.html.twig', array(
            'clients' => $clients, 'bundlename' => $BundleName));
    }

    public function manageClientsAction()
    {
        $context = $this->container->get('security.context');

        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');

        //auth
        $request = $this->get('request');
        $conn = $this->get_Store_DB_Object();
        //get all cliets with number of bundles and number of applications that every client have
        $stmt = $conn->prepare('SELECT *,(SELECT COUNT(*) FROM Bundle,Subscription where BundleID=Bundle.ID and ClientID=Client.ID) as numberOfBundles,((SELECT count(*) FROM ControllerInstallation WHERE ControllerInstallation.ClientID=Client.ID)+(SELECT COUNT(*)FROM DongleInstallation WHERE DongleInstallation.ClientID=Client.ID)) as numberOfApplications from Client');
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $clients = $stmt->fetchAll();
        return $this->render('DashboardBundle:Sales:manage-clients.html.twig', array(
            'clients' => $clients));
    }

    public function showClientBundlesAction($slug)
    {
        $context = $this->container->get('security.context');

        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');

        //auth
        $request = $this->get('request');
        $ClientID = $slug;
        //connect to database
        $conn = $this->get_Store_DB_Object();
        //get applications with number of clients
        if ($request->getMethod() == 'POST') {
            $delete = $request->request->get('delete');
            $edit = $request->request->get('edit');
            $deletedbundlename = $request->request->get('deletedbundle');

            if ($delete) {
                $this->deleteSubscribtion($deletedbundlename, $request, $ClientID);
            } else if ($edit) {
                $this->editBundle($request, $ClientID);
            } else//here it's add operation
            {
                $this->addBundle($request, $ClientID);
            }
        }
        //if it's not post request then show client bunles
        $stmt = $conn->prepare('Select * from Subscription,Bundle where BundleID=Bundle.ID and ClientID=?');
        try {
            $stmt->execute([$ClientID]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $bundles = $stmt->fetchAll();
        //this for the list of bundles that client are not subscripe when add subscription
        $stmt = $conn->prepare('select * from Bundle where ID not in (Select BundleID from Subscription,Bundle where BundleID=Bundle.ID and ClientID=?)');
        $stmt2 = $conn->prepare('select Name from Client where ID=?');
        try {
            $stmt->execute([$ClientID]);
            $stmt2->execute([$ClientID]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $allbundles = $stmt->fetchAll();
        $ClientName = $stmt2->fetchAll()[0];
        return $this->render('DashboardBundle:Sales:show-client-bundles.html.twig', array(
            'bundles' => $bundles, 'allbundles' => $allbundles, 'clientname' => $ClientName));

    }

    public function deleteSubscribtion($deletedbundlename, $request, $ClientID)
    {
        $conn = $this->get_Store_DB_Object();
        //check if the user select row if not throw exception
        if ($deletedbundlename) {
            //find id for the bundle
            $stmt = $conn->prepare('Select ID from Bundle where Name=?');
            try {
                $stmt->execute([$deletedbundlename]);
            } catch (\PDOException $e) {
                $error = 'Operation Aborted ..' . $e->getMessage();
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            }
            $deletedbundleID = $stmt->fetchAll();
            $deletedbundleID = $deletedbundleID[0][0];
            //delete subscription
            try {
                $stmt = $conn->prepare('delete from Subscription Where ClientID=? and BundleID=? ');
                $stmt->execute([$ClientID, $deletedbundleID]);
                $stmt = $conn->prepare('select * from BundleApplication,Application where BundleID=? and ApplicationID=Application.ID ');
                $stmt->execute([$deletedbundleID]);
                While ($app = $stmt->fetch()) {
                    if ($app['Type'] == 'dongle') {
                        $stmt1 = $conn->prepare('delete from DongleInstallation where Subscription=true and ApplicationID=? and ClientID=? and (select  count(*)from BundlesTracking where BundlesTracking.ApplicationID=? and UserID=?) <= 1 ');
                        $stmt1->execute([$app['ApplicationID'], $ClientID, $app['ApplicationID'], $ClientID]);
                    } else {
                        $stmt1 = $conn->prepare('delete from ControllerInstallation where Subscription=true and ApplicationID=? and ClientID=? and (select  count(*)from BundlesTracking where BundlesTracking.ApplicationID=? and UserID-?)<=1 ');
                        $stmt1->execute([$app['ApplicationID'], $ClientID, $app['ApplicationID'], $ClientID]);
                    }
                    $stmt1 = $conn->prepare('delete from BundlesTracking where ApplicationID=? and UserID =? and BundleID=?');
                    $stmt1->execute([$app['ApplicationID'], $ClientID, $deletedbundleID]);
                }
            } catch (\PDOException $e) {
                $error = 'Operation Aborted ..' . $e->getMessage();
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            }
            $request->getSession()->getFlashBag()->add('success', "Bundle Removed Successfully");
            return $this->redirect($request->headers->get('referer'));
        } else {
            $error = "Select Bundle to remove";
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
    }

    public function editBundle($request, $ClientID)
    {
        $conn = $this->get_Store_DB_Object();
        $bundlename = $request->request->get('editbundle');
        $StartDate = $request->request->get('StartDate');
        $EndDate = $request->request->get('EndDate');
        //check data is it valid or not if not throw exception.
        if ($EndDate && $StartDate) {
            //get ID of the bundle first
            $stmt = $conn->prepare('Select ID from Bundle where Name=?');
            try {
                $stmt->execute([$bundlename]);
            } catch (\PDOException $e) {
                $error = 'Operation Aborted ..' . $e->getMessage();
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            }
//update the bundle now
            $bundleID = $stmt->fetchAll();
            $bundleID = $bundleID[0][0];
            $stmt = $conn->prepare('update Subscription Set Start = ? , End = ? Where ClientID=? and BundleID=? ');

            try {
                $stmt->execute([$StartDate, $EndDate, $ClientID, $bundleID]);
            } catch (\PDOException $e) {
                $error = 'Operation Aborted ..' . $e->getMessage();
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            }

            $request->getSession()->getFlashBag()->add('success', "Bundle Edited Successfully");
            return $this->redirect($request->headers->get('referer'));
        }//

        $error = "Fill All Fields";
        $request->getSession()->getFlashBag()->add('danger', $error);
        return $this->redirect($request->headers->get('referer'));
    }

    public function addBundle($request, $ClientID)
    {
        $conn = $this->get_Store_DB_Object();
        $newbundleID = $request->request->get('newbundle');
        $StartDate = $request->request->get('StartDate');
        $EndDate = $request->request->get('EndDate');
        //check if data is valid if not throw exception
        if ($newbundleID && $StartDate && $EndDate) {

            $stmt = $conn->prepare('Insert into Subscription (BundleID,ClientID,Start,End) values (?,?,?,?) ');
            $stmt->execute([$newbundleID, $ClientID, $StartDate, $EndDate]);
            $stmt = $conn->prepare('select *, (select max(Version) from Version where ApplicationID=Application.ID ) as Version from BundleApplication,Application where BundleID=? and BundleApplication.ApplicationID=Application.ID');
            $stmt->execute([$newbundleID]);
            try {
                While ($bundleApplication = $stmt->fetch()) {
                    try {
                        $stmt2 = $conn->prepare('Insert into BundlesTracking (UserID, ApplicationID, BundleID) VALUES (?,?,?)');
                        $stmt2->execute([$ClientID, $bundleApplication['ApplicationID'], $newbundleID]);
                        if ($bundleApplication['Type'] == 'dongle') {
                            $stmt2 = $conn->prepare('select * from  DongleInstallation where ApplicationID=? and ClientID=?');
                            $stmt2->execute([$ClientID, $bundleApplication['ApplicationID']]);
                            if ($stmt2->fetch())
                                continue;
                            $stmt2 = $conn->prepare('Insert into DongleInstallation (ClientID,ApplicationID,Version,Status,Subscription) values (?,?,?,(select PK from Status where Status.status="none"),true) ');
                        } else {
                            $stmt2 = $conn->prepare('select * from  ControllerInstallation where ApplicationID=? and ClientID=?');
                            $stmt2->execute([$ClientID, $bundleApplication['ApplicationID']]);
                            if ($stmt2->fetch())
                                continue;
                            $stmt2 = $conn->prepare('Insert into ControllerInstallation (ClientID,ApplicationID,Version,Status,Subscription) values (?,?,?,(select PK from Status where Status.status="none"),true) ');
                        }

                        $stmt2->execute([$ClientID, $bundleApplication['ApplicationID'], $bundleApplication['Version']]);

                    } catch (\PDOException $e) {

                    }
                }

            } catch (\PDOException $e) {
                $error = 'Operation Aborted ..' . $e->getMessage();
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            }
            $request->getSession()->getFlashBag()->add('success', "Bundle Added Successfully");
            return $this->redirect($request->headers->get('referer'));
        }
        $error = "Fill All Fields";
        $request->getSession()->getFlashBag()->add('danger', $error);
        return $this->redirect($request->headers->get('referer'));
    }

    public function showClientApplicationsAction($slug)
    {
        $context = $this->container->get('security.context');
        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');
        //auth
        $request = $this->get('request');
        $ClientID = $slug;
        //connect to database
        $conn = $this->get_Store_DB_Object();
        if ($request->getMethod() == 'POST') {
            $delete = $request->request->get('delete');
            $deletedappname = $request->request->get('deletedapp');
//            $error =$delete;
//            $request->getSession()->getFlashBag()->add('danger', $error);

            if ($delete) {
                $this->removeApplication($deletedappname, $request, $ClientID);
            } else {
                $this->addApplication($request, $ClientID);
            }
        }
        //get Tablet applications
        $stmt = $conn->prepare('Select *,
                                (select WebDownloadDate from ControllerInstallation as cont 
                                    where clientID=outc.ClientID  and ApplicationID=Application.ID 
                                ) as WebDownloadDate,
                                 (select DeviceDownloadDate from ControllerInstallation as cont 
                                    where clientID=outc.ClientID  and ApplicationID=Application.ID 
                                ) as DeviceDownloadDate,
                                (select Subscription from ControllerInstallation as cont 
                                    where clientID=outc.ClientID  and ApplicationID=Application.ID 
                                ) as Subscription,
                                (select Version from ControllerInstallation as cont 
                                    where clientID=outc.ClientID  and ApplicationID=Application.ID 
                                ) as Version ,
                                (select InstallationDate from ControllerInstallation as cont 
                                    where clientID=outc.ClientID  and ApplicationID=Application.ID 
                                ) as InstallationDate,(select ss.status from ControllerInstallation as cont,Status as ss
                                    where clientID=outc.ClientID  and ApplicationID=Application.ID 
                                    and ss.PK=cont.Status
                                ) as Status
                                from ControllerInstallation as outc ,Application where outc.ApplicationID=Application.ID and ClientID=?');
        // get Dongle application
        $stmt1 = $conn->prepare('Select *,
                                (select WebDownloadDate from DongleInstallation as don 
                                    where clientID=outc.ClientID  and ApplicationID=outc.ApplicationID 
                                ) as WebDownloadDate,
                                (select DeviceDownloadDate from DongleInstallation as don 
                                    where clientID=outc.ClientID  and ApplicationID=outc.ApplicationID 
                                ) as DeviceDownloadDate,
                                (select Subscription from DongleInstallation as don 
                                    where clientID=outc.ClientID  and ApplicationID=outc.ApplicationID 
                                ) as Subscription,
                                (select Version from DongleInstallation as don 
                                    where clientID=outc.ClientID  and ApplicationID=outc.ApplicationID 
                                ) as Version ,
                                (select InstallationDate from DongleInstallation as don 
                                    where clientID=outc.ClientID  and ApplicationID=outc.ApplicationID 
                                ) as InstallationDate,
                                (select ss.status from DongleInstallation as don,Status as ss
                                    where clientID=outc.ClientID  and ApplicationID=outc.ApplicationID 
                                    and ss.PK=don.Status
                                ) as Status
                                from DongleInstallation as outc ,Application where ApplicationID=Application.ID and ClientID=?');
        try {
            $stmt->execute([$ClientID]);
            $stmt1->execute([$ClientID]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $tabletApplications = $stmt->fetchAll();
        $dongleApplications = $stmt1->fetchAll();
        //get the application that the client does not have
        $stmt = $conn->prepare('Select *  from Application where Application.ID not in(select  ApplicationID from Purchase where ClientID=?)');
        $stmt2 = $conn->prepare('Select Name from Client where ID=?');
        try {
            $stmt->execute([$ClientID]);
            $stmt2->execute([$ClientID]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $apps = $stmt->fetchAll();
        $ClientName = $stmt2->fetchAll()[0];
        $app_VName = Array();
        foreach (array_merge($tabletApplications, $dongleApplications) as $app) {
            $stmt = $conn->prepare('select VersionName from Version where Version.ApplicationID=? and Version.Version=?');
            $stmt->execute([$app['ID'],$app['Version']]);
            $app['VersionName'] = $stmt->fetch()[0];
            array_push($app_VName,$app);
        }
        ;
        return $this->render('DashboardBundle:Sales:show-client-applications.html.twig', array(
            'applications' => $app_VName, 'apps' => $apps, 'clientname' => $ClientName));
    }

    public function get_Main_DB_Object()
    {
        $dbname = $this->container->getParameter('database_name');
        $username = $this->container->getParameter('database_user');
        $password = $this->container->getParameter('database_password');
        $servername = $this->container->getParameter('database_host');

        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    }

    public function removeApplication($deletedappname, $request, $ClientID)
    {
        $conn = $this->get_Store_DB_Object();
        if ($deletedappname) {
            $stmt = $conn->prepare('Select ID from Application where Name=?');
            try {
                $stmt->execute([$deletedappname]);
            } catch (\PDOException $e) {
                $error = 'Operation Aborted ..' . $e->getMessage();
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            }
            $deletedappID = $stmt->fetchAll();
            $deletedappID = $deletedappID[0][0];
            try {
                $stmt = $conn->prepare('delete from Purchase Where ClientID=? and ApplicationID=? ');
                $stmt->execute([$ClientID, $deletedappID]);
                $stmt = $conn->prepare('Select * from Application,Version where Application.id=? and ApplicationID=Application.ID');
                $stmt->execute([$deletedappID]);
                $app = $stmt->fetch();
                $this->romoveApplicationOnType($app, $request, $ClientID, $deletedappID);
            } catch (\PDOException $e) {
                $error = 'Operation Aborted ..' . $e->getMessage();
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            }
            $request->getSession()->getFlashBag()->add('success', "Application Removed Successfully");
            return $this->redirect($request->headers->get('referer'));
        }

        $error = "Select Application to remove";
        $request->getSession()->getFlashBag()->add('danger', $error);
        return $this->redirect($request->headers->get('referer'));
    }

    public function romoveApplicationOnType($app, $request, $ClientID, $deletedappID)
    {
        $conn = $this->get_Store_DB_Object();
        if ($app['Type'] == "dongle") {
            //todo check if it is belong to user subscription

            $stmt = $conn->prepare('select * from Subscription as sb,BundleApplication as ba ,DongleInstallation as dn where dn.ClientID = ? and sb.ClientID =dn.ClientID and sb.BundleID=ba.BundleID and dn.ApplicationID=ba.ApplicationID and dn.ApplicationID= ? ');
            $stmt->execute([$ClientID, $app['ApplicationID']]);
            $result = $stmt->fetch();
            if ($result && $result['Subscription']) {
                $error = 'This is application belong to subscription can not be deleted';
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            } else if ($result && !$result['Subscription']) {
                $stmt = $conn->prepare('update DongleInstallation set Subscription=true Where ClientID=? and ApplicationID=? ');
            } else {
                $stmt = $conn->prepare('delete from DongleInstallation Where ClientID=? and ApplicationID=? ');
            }
            $stmt->execute([$ClientID, $deletedappID]);
        } else {
            $stmt = $conn->prepare('select * from Subscription as sb,BundleApplication as ba ,ControllerInstallation as dn where dn.ClientID = ? and sb.ClientID =dn.ClientID and sb.BundleID=ba.BundleID and dn.ApplicationID=ba.ApplicationID and dn.ApplicationID= ? ');
            $stmt->execute([$ClientID, $app['ApplicationID']]);
            $result = $stmt->fetch();
            if ($result && $result['Subscription']) {
                $error = 'This is application belong to subscription can not be deleted';
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            } else if ($result && !$result['Subscription']) {
                $stmt = $conn->prepare('update ControllerInstallation set Subscription=true Where ClientID=? and ApplicationID=? ');
            } else {
                $stmt = $conn->prepare('delete from ControllerInstallation Where ClientID=? and ApplicationID=? ');
            }
            $stmt->execute([$ClientID, $deletedappID]);
        }
    }

    public function addApplication($request, $ClientID)
    {
        $conn = $this->get_Store_DB_Object();
        $newappID = $request->request->get('newapp');
        if ($newappID) {
            try {
                $stmt = $conn->prepare('Insert into Purchase (ApplicationID,ClientID,Date) values (?,?,?) ');
                $stmt->execute([$newappID, $ClientID, date('y-m-d')]);
                $stmt = $conn->prepare('Select *,max(Version)as Version from Application,Version where Application.id=? and ApplicationID=Application.ID');
                $stmt->execute([$newappID]);
                $app = $stmt->fetch();

                $this->addApplicationOnType($app, $request, $newappID, $ClientID);

            } catch (\PDOException $e) {
                $error = 'Operation Aborted ..' . $e->getMessage();
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            }
            $request->getSession()->getFlashBag()->add('success', "Application Added Successfully");
            return $this->redirect($request->headers->get('referer'));
        }
        $error = "Select Application to add";
        $request->getSession()->getFlashBag()->add('danger', $error);
        return $this->redirect($request->headers->get('referer'));
    }

    public function addApplicationOnType($app, $request, $newappID, $ClientID)
    {
        $conn = $this->get_Store_DB_Object();
        if ($app['Type'] == "dongle") {
            try {
                $stmt = $conn->prepare('Insert into DongleInstallation  (ApplicationID,ClientID,Status,Version,Subscription) values (?,?,(Select PK from Status where Status.status="none"),?,false ) ');
                $stmt->execute([$newappID, $ClientID, $app['Version']]);
            } catch (\PDOException $e) {
                $error = 'Operation Aborted ..' . $e->getMessage();
                $request->getSession()->getFlashBag()->add('danger', $error);
                $stmt = $conn->prepare('update DongleInstallation set Subscription = false where ApplicationID=? and ClientID=? and Version=?');
                $stmt->execute([$newappID, $ClientID, $app['Version']]);
            }

        } else {
            try {
                $stmt = $conn->prepare('Insert into ControllerInstallation (ApplicationID,ClientID,Status,Version,Subscription) values (?,?,(Select PK from Status where Status.status="none"),?,false) ');
                $stmt->execute([$newappID, $ClientID, $app['Version']]);
            } catch (\PDOException $e) {

                $stmt = $conn->prepare('update ControllerInstallation set Subscription=false where ApplicationID=? and ClientID=? and Version=?');
                $stmt->execute([$newappID, $ClientID, $app['Version']]);
            }
        }
    }

    public function showClientProfileAction($slug)
    {
        $context = $this->container->get('security.context');

        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');

        //auth
        $request = $this->get('request');
        $ClientID = $slug;
        //connect to database
        $conn = $this->get_Store_DB_Object();

        if ($request->getMethod() == 'POST') {
            //this for the update operation
            $Name = $request->request->get('Name');
            $Email = $request->request->get('Email');
            $Password = $request->request->get('Password');
            $Address = $request->request->get('Address');
            $ID = $request->request->get('ID');
            $stmt = $conn->prepare('update  Client Set ID=?, Name=?,Email=?,Password=?,Address=? where ID=?');
            try {
                $stmt->execute([$ID, $Name, $Email, $Password, $Address, $ClientID]);
            } catch (\PDOException $e) {
                $error = 'Operation Aborted ..' . $e->getMessage();
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            }
            $request->getSession()->getFlashBag()->add('success', "Client Profile Updated Successfully");
            return $this->redirect($this->generateUrl('dashboard_sales_client_profile', array('slug' => $ID)));
        }
        //if not update show the client
        $stmt = $conn->prepare('select * from Client where ID=?');
        try {
            $stmt->execute([$ClientID]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $client = $stmt->fetchAll();
        $client = $client[0];
        $stmt = $conn->prepare('select (select count(*) from Subscription where ClientID=Client.ID) as subcount , (((SELECT count(*) FROM ControllerInstallation WHERE ControllerInstallation.ClientID=Client.ID)+(SELECT COUNT(*)FROM DongleInstallation WHERE DongleInstallation.ClientID=Client.ID))) as appcount from Client where ID=?');
        try {
            $stmt->execute([$ClientID]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $counts = $stmt->fetchAll();
        $counts = $counts[0];
        return $this->render('DashboardBundle:Sales:client-profile-page.html.twig', array(
            'client' => $client, 'counts' => $counts, 'add' => false));
    }

    public function addClientAction()
    {
        $context = $this->container->get('security.context');

        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');

        //auth
        $request = $this->get('request');
        $conn = $this->get_Store_DB_Object();


        if ($request->getMethod() == 'POST') {
            $Name = $request->request->get('Name');
            $Email = $request->request->get('Email');
            $Password = $request->request->get('Password');
            $Address = $request->request->get('Address');
            $ID = $request->request->get('ID');
            $stmt = $conn->prepare('Insert into  Client (ID,Name,Email,Password,Address) values (?,?,?,?,?)');
            try {
                $stmt->execute([$ID, $Name, $Email, $Password, $Address]);
            } catch (\PDOException $e) {
                $error = 'Operation Aborted ..' . $e->getMessage();
                $request->getSession()->getFlashBag()->add('danger', $error);
                return $this->redirect($request->headers->get('referer'));
            }
            $request->getSession()->getFlashBag()->add('success', "Client Added Successfully");
            return $this->redirect($this->generateUrl('dashboard_sales_manage_clients'));
        }
        return $this->render('DashboardBundle:Sales:client-profile-page.html.twig', array(
            'add' => true));
    }

    public function deleteClientAction($slug)
    {
        $context = $this->container->get('security.context');

        if (!$context->isGranted('IS_AUTHENTICATED_FULLY'))
            return $this->render('DashboardBundle:Homepage:index.html.twig');

        //auth
        $request = $this->get('request');
        $ID = $slug;
        $conn = $this->get_Store_DB_Object();

        $deleteFromPurchase = $conn->prepare('delete from Purchase where ClientID=?');
        $deleteFromSubscription = $conn->prepare('delete from Subscription where ClientID=?');
        $deleteFromContins = $conn->prepare('delete from ControllerInstallation where ClientID=?');
        $deleteFromDongelins = $conn->prepare('delete from DongleInstallation where ClientID=?');
        $deleteFromClient = $conn->prepare('delete from Client where ID=?');
        try {
            $deleteFromPurchase->execute([$ID]);
            $deleteFromSubscription->execute([$ID]);
            $deleteFromContins->execute([$ID]);
            $deleteFromDongelins->execute([$ID]);
            $deleteFromClient->execute([$ID]);
        } catch (\PDOException $e) {
            $error = 'Operation Aborted ..' . $e->getMessage();
            $request->getSession()->getFlashBag()->add('danger', $error);
            return $this->redirect($request->headers->get('referer'));
        }
        $request->getSession()->getFlashBag()->add('success', "Client Deleted Successfully");
        return $this->redirect($this->generateUrl('dashboard_sales_manage_clients'));

    }
}
