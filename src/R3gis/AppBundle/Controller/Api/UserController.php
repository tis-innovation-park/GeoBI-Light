<?php

namespace R3gis\AppBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use R3gis\AppBundle\Entity\User;
use R3gis\AppBundle\Security\Firewall\WsseListener;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

/**
 * @Route("/user")
 */
class UserController extends Controller {

    const LIMIT = 50;  // Max 50 rows

    /**
     * @api {GET} /user/users.json/  List users
     * @apiName listAction
     * @apiGroup user
     *
     * @apiDescription Returns list of users as json, but only to admins
     * 
     * @apiParam {Integer} id    userid
     * 
     */
    /**
     * @Route("/users.json", methods = {"GET"}, name="r3gis.api.user.list")
     */
    public function listAction(Request $request) {
        $response = new JsonResponse();

        if (!$this->get('security.context')->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();
        $qb = $em->getRepository("R3gisAppBundle:User")->createQueryBuilder('u');

        // Filters
        // TODO: CHANGE user id
        //$qb->andWhere("m.private=FALSE OR IDENTITY(m.user)=1");  // Only private map OR my map
        //if ($request->query->get('q')) { // Text search
        //    // Implement ILIKE
        //    $qb->andWhere("UPPER(m.name) LIKE UPPER(:text) OR UPPER(m.description) LIKE UPPER(:text)");
        //    $qb->setParameter('text', '%' . $request->query->get('q') . '%');
        //}
        //if ($request->query->get('lang')) { // Language filter (multiple values allowed)
        //    $qb->andWhere("m.language IN (:language)");
        //    $qb->setParameter('language', explode(' ', $request->query->get('lang')));
        //}
        // TODO: CHANGE user id
        //if ($request->query->get('priv_only', 'f') == 't') {
        //    $qb->andWhere("m.private = TRUE AND IDENTITY(m.user)=1");
        //}
        // Count record (before order and limit)
        $qb2 = clone($qb); // Clone needed...
        $total = $qb2->select('COUNT(u)')
                ->getQuery()
                ->getSingleScalarResult();

        // Order by
        /* if ($request->query->get('order')) { // Order by (multiple values allowed)
          $orders = explode(' ', $request->query->get('order'));
          foreach ($orders as $ord) {
          switch ($ord) {
          case 'recent':
          $qb->addOrderBy('m.modDate', 'ASC');
          break;
          case 'click':
          $qb->addOrderBy('m.clickCount', 'ASC');
          break;
          break;
          }
          }
          } else {
          $qb->addOrderBy('m.name', 'ASC');
          $qb->addOrderBy('m.id', 'ASC');
          } */

        // Limit
        if ($request->query->get('limit')) {
            $qb->setMaxResults($request->query->get('limit'));
        } else {
            $qb->setMaxResults(self::LIMIT);
        }

        // Offset
        if ($request->query->get('offset')) {
            $qb->setFirstResult($request->query->get('offset'));
        }

        $result = $qb
                ->getQuery()
                ->getResult();

        // Custom normalization of datetime
        $normalizer = new GetSetMethodNormalizer();
        $callback = function ($dateTime) {
            return $dateTime instanceof \DateTime ? $dateTime->format(\DateTime::ISO8601) : '';
        };
        $normalizer->setCallbacks(array('insDate' => $callback, 'modDate' => $callback));

        $serializer = new Serializer(array($normalizer), array(new JsonEncoder()));
        $jsonData = $serializer->normalize($result);
        
        foreach ($jsonData as $key => $value) {
            unset($jsonData[$key]["password"]);
            unset($jsonData[$key]["salt"]);
            unset($jsonData[$key]["username"]);
        }

        $response->setData(array(
            'success' => true,
            'total' => $total,
            'result' => $jsonData
        ));

        return $response;
    }
    
    /**
     * @api {DELETE} /user/{id}/  Delete user
     * @apiName deleteAction
     * @apiGroup user
     *
     * @apiDescription Returns json with success=true, when user successfully deleted
     * 
     * @apiParam {Integer} id    userid
     * 
     */
    /**
     * @Route("/{id}", methods = {"DELETE"})
     */
    public function deleteAction(Request $request, $id="") {
        $response = new JsonResponse();

        if (!$this->get('security.context')->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException("Only Admins are allowed to delete users.");
        }

        if($id==null || $id==='') {
            throw new BadRequestHttpException('Invalid id specified');
        }

        $em = $this->getDoctrine()->getManager();
        $userRepo = $em->getRepository('R3gisAppBundle:User');
        $user = $userRepo->findOneById($id);
        if($user == null) {
            throw new NotFoundHttpException('Couldn\'t find any user with that id.');
        }

        $em->remove($user);
        $em->flush();

        $response->setStatusCode(200);
        $response->setData(array('success'=> true,));
        return $response;
    }
    
    /**
     * @api {PUT} /user/{id}/  Modify user
     * @apiName modifyAction
     * @apiGroup user
     *
     * @apiDescription Returns json with success=true, when user successfully modified
     * 
     * @apiParam {object} user        json user object with properties to be changed
     * 
     */
    /**
     * @Route("/{id}", methods = {"PUT"})
     */
    public function modifyAction(Request $request, $id="") {
        $response = new JsonResponse();
        
        if (!$this->get('security.context')->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException();
        }

        $userchanges = json_decode($request->getContent());

        if($id==null || $id==='' || $userchanges==null) {
            throw new BadRequestHttpException('Invalid userid or userchanges specified');
        }

        $em = $this->getDoctrine()->getManager();
        $userRepo = $em->getRepository('R3gisAppBundle:User');
        $user = $userRepo->findOneById($id);
        if($user == null) {
            throw new NotFoundHttpException('Couldn\'t find any user with that id.');
        }
        
        if(empty($userchanges)){
            throw new BadRequestHttpException('Invalid user object given');
        }

        //Encode password
        if(property_exists($userchanges, "password")) {
            $encoder = $this->get('security.encoder_factory')->getEncoder($user);
            $password = $encoder->encodePassword($userchanges->password, null); 
            $userchanges->password = $password;
        }

        if(property_exists($userchanges,"id")) {
            unset($userchanges->id);
        }
        
        if(property_exists($userchanges,"roles")) {
            $groupname = str_replace("ROLE_","", $userchanges->roles[0]);
            $groupRepo = $em->getRepository('R3gisAppBundle:Group');
            $group = $groupRepo->findOneByName($groupname);
            if($group!=null) {
                $userchanges->group=$group;
                
            }
            unset($userchanges->roles);
            //unset($userchanges->group);
        }
        
        foreach ($userchanges as $key => $value) {
            if($value== null) {
                continue;
            }
            if( $key === 'validationHashCreatedTime' || 
                $key === 'resetPasswordHashCreatedTime' || 
                $key === 'pwLastChange' || 
                $key === 'lastLogin' || 
                $key === 'modifiedDate'
            ) {
                
                $date = new \DateTime();
                $date->setTimestamp($value->timestamp);
                //$userchanges->$key = $date;
                $value = $date;
            }
                
            $method = 'set' . ucfirst($key);
            if(method_exists($user, $method)) {
                $user->$method($value);
            } else {
                throw new BadRequestHttpException('Invalid property for userobject: '.$key);
            }
        }
        $em->flush();

        $response->setStatusCode(200);
        $response->setData(array('success'=> true,));
        return $response;
    }
    
    /**
     * @api {post} /user/login/  login user
     * @apiName loginAction
     * @apiGroup user
     *
     * @apiDescription Returns json with success=true and object user with user infos, when successful
     * 
     * @apiParam {String} email          email
     * @apiParam {String} password          password
     * 
     */
    /**
     * @Route("/login", methods = {"POST"})
     */
    public function loginAction(Request $request) {
        $response = new JsonResponse();

        $json = json_decode($request->getContent());
        $email = $json->email;
        $password = $json->password;

        $this->get('logger')->info("email: {$email}, password: {$password}");
        try {
            $user = $this->getDoctrine()->getRepository('R3gisAppBundle:User')->loadUserByUsername($email);
        } catch (UsernameNotFoundException $userNotFound) {
            throw new AccessDeniedHttpException("Wrong Email and/or password.");
        }

        $created = date('c');
        $nonce = substr(md5(uniqid('nonce_', true)), 0, 16);
        $nonceHigh = base64_encode($nonce);

        $factory = $this->get('security.encoder_factory');
        $encoder = $factory->getEncoder($user);
        $passwordEnc = $encoder->encodePassword($password, $user->getSalt());

        $passwordDigest = base64_encode(sha1($nonce . $created . $passwordEnc, true));
        $header = "UsernameToken Username=\"{$email}\", PasswordDigest=\"{$passwordDigest}\", Nonce=\"{$nonceHigh}\", Created=\"{$created}\"";

        // validate wsse header
        $wsseListener = new WsseListener($this->get('security.context'), $this->get('security.authentication.manager'));
        try {
            $wsseListener->validateWsseToken($header);

            $response->setStatusCode(200);

            // add headers
            $response->headers->set("Authorization", 'WSSE profile="UsernameToken"');
            $response->headers->set("X-WSSE", "UsernameToken Username=\"{$email}\", PasswordDigest=\"{$passwordDigest}\", Nonce=\"{$nonceHigh}\", Created=\"{$created}\"");

            $response->setData(array(
                'success' => true,
                'result' => 
                    array( 
                        'roles' => $user->getRoles(),
                        'name' => $user->getName(),
                        'email' => $user->getEmail(),
                        'lastIp' => $user->getLastIp(),
                        'lastLogin' => $user->getLastLogin(),
                        'language' => $user->getLanguage(),
                    ),
            ));
        } catch (AuthenticationException $failed) {
            throw new AccessDeniedHttpException("Wrong Email and/or password, or you have not activated your account yet.");
        }
       
        return $response;
    }
    
    /**
     * @api {get} /user/logout/  logout user
     * @apiName logoutAction
     * @apiGroup user
     *
     * @apiDescription Returns json with success=true, when successful
     * 
     * 
     */
    /**
     * @Route("/logout", methods = {"GET"})
     */
    public function logoutAction(Request $request) {
        
        //@TODO logout, done clientside for now. dummy route
        $response = new JsonResponse();
        $response->setStatusCode(200);
        $response->setData(array('success' => true,));
        return $response;
    }
    
}
