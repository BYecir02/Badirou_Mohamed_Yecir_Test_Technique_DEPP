<?php

namespace App\Controller;

use App\Entity\Infos;
use App\Form\InfosType;
use App\Repository\InfosRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/informations')]
final class InfosController extends AbstractController
{
    #[Route(name: 'app_infos_index', methods: ['GET'])]
    public function index(InfosRepository $infosRepository): Response
    {
        return $this->render('infos/index.html.twig', [
            'infos' => $infosRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_infos_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $info = new Infos();
        $form = $this->createForm(InfosType::class, $info);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($info);
            $entityManager->flush();

            return $this->redirectToRoute('app_infos_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('infos/new.html.twig', [
            'info' => $info,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_infos_show', methods: ['GET'])]
    public function show(Infos $info): Response
    {
        return $this->render('infos/show.html.twig', [
            'info' => $info,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_infos_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Infos $info, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(InfosType::class, $info, ['is_edit' => true,]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_infos_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('infos/edit.html.twig', [
            'info' => $info,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_infos_delete', methods: ['POST'])]
    public function delete(Request $request, Infos $info, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$info->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($info);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_infos_index', [], Response::HTTP_SEE_OTHER);
    }
}
