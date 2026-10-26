<?php
namespace App\Controller;

use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CatalogController extends AbstractController
{
    #[Route('/danh-muc', name:'app_catalog', methods:['GET'])]
    public function index(Request $request, BookRepository $books): Response
    {
        $filters = [];
        foreach (['q','category','min','max','sort'] as $key) { $filters[$key] = trim($request->query->getString($key)); }
        $errors = [];
        if (mb_strlen($filters['q']) > 200) { $filters['q'] = mb_substr($filters['q'],0,200); }
        if ($filters['category'] !== '' && !preg_match('/^[1-9][0-9]{0,8}$/D',$filters['category'])) { $errors[]='Loại sách không hợp lệ.'; $filters['category']=''; }
        foreach (['min','max'] as $key) {
            if ($filters[$key] !== '' && !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$filters[$key])) { $errors[]='Khoảng giá không hợp lệ.'; $filters[$key]=''; }
        }
        if ($filters['min'] !== '' && $filters['max'] !== '' && BookRepository::cents($filters['min']) > BookRepository::cents($filters['max'])) { $errors[]='Giá tối thiểu không được lớn hơn giá tối đa.'; }
        if (!in_array($filters['sort'],['name','price_asc','price_desc'],true)) { $filters['sort']='name'; }
        $page = filter_var($request->query->getString('page','1'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: 1;
        $result = $books->catalog($filters,$page);
        return $this->render('catalog/index.html.twig', $result + ['categories'=>$books->categories(),'filters'=>$filters,'errors'=>$errors]);
    }
}
