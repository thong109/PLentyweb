<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Customize\Controller\Admin\Customer;

use Eccube\Controller\Admin\AbstractCsvImportController;
use Eccube\Entity\Customer;
use Eccube\Form\Type\Admin\CsvImportType;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\Master\PrefRepository;
use Eccube\Repository\Master\SexRepository;
use Eccube\Repository\Master\JobRepository;
use Eccube\Repository\Master\CustomerStatusRepository;
use Eccube\Util\CacheUtil;
use Eccube\Util\StringUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class CsvImportController extends AbstractCsvImportController
{
    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var EncoderFactoryInterface
     */
    protected $encoderFactory;

    /**
     * @var PrefRepository
     */
    protected $prefRepository;

    /**
     * @var SexRepository
     */
    protected $sexRepository;

    /**
     * @var JobRepository
     */
    protected $jobRepository;

    /**
     * @var CustomerStatusRepository
     */
    protected $customerStatusRepository;

    private $errors = [];

    /**
     * CsvImportController constructor.
     *
     * @throws \Exception
     */
    public function __construct(
        CustomerRepository $customerRepository,
        ValidatorInterface $validator,
        EncoderFactoryInterface $encoderFactory,
        PrefRepository $prefRepository,
        SexRepository $sexRepository,
        JobRepository $jobRepository,
        CustomerStatusRepository $customerStatusRepository
    )
    {
        $this->customerRepository = $customerRepository;
        $this->validator = $validator;
        $this->encoderFactory = $encoderFactory;
        $this->prefRepository = $prefRepository;
        $this->sexRepository = $sexRepository;
        $this->jobRepository = $jobRepository;
        $this->customerStatusRepository = $customerStatusRepository;
    }

    /**
     * 商品登録CSVアップロード
     *
     * @Route("/%eccube_admin_route%/customer/csv_upload", name="admin_customer_csv_import")
     * @Template("@admin/Customer/csv.twig")
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function csvCustomer(Request $request, CacheUtil $cacheUtil)
    {
        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
        $headers = $this->getCustomerCsvHeader();
        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $formFile = $form['import_file']->getData();
                if (!empty($formFile)) {
                    log_info('顧客CSV登録開始');
                    $data = $this->getImportData($formFile);
                    if ($data === false) {
                        $this->addErrors(trans('admin.common.csv_invalid_format'));
                        return $this->renderWithError($form, $headers, false);
                    }
                    $getId = function ($item) {
                        return $item['id'];
                    };
                    $requireHeader = array_keys(array_map($getId, array_filter($headers, function ($value) {
                        return $value['required'];
                    })));
                    $headerByKey = array_flip(array_map($getId, $headers));
                    $columnHeaders = $data->getColumnHeaders();

                    if (count(array_diff($requireHeader, $columnHeaders)) > 0) {

                        $this->addErrors(trans('admin.common.csv_invalid_format'));
                        return $this->renderWithError($form, $headers, false);
                    }
                    $size = count($data);
                    if ($size < 1) {
                        $this->addErrors(trans('admin.common.csv_invalid_no_data'));

                        return $this->renderWithError($form, $headers, false);
                    }
                    $headerSize = count($columnHeaders);

                    $this->entityManager->getConfiguration()->setSQLLogger(null);
                    $this->entityManager->getConnection()->beginTransaction();
                    foreach ($data as $row) {
                        $line = $data->key() + 1;
                        $previous_password = null;

                        if ($headerSize != count($row)) {
                            $message = trans('admin.common.csv_invalid_format_line', ['%line%' => $line]);
                            $this->addErrors($message);
                            return $this->renderWithError($form, $headers);
                        }

                        if (!isset($row[$headerByKey['id']]) || StringUtil::isBlank($row[$headerByKey['id']])) {
                            $Customer = new Customer();
                            $this->entityManager->persist($Customer);
                        } else {
                            if (preg_match('/^\d+$/', $row[$headerByKey['id']])) {
                                $Customer = $this->customerRepository->find($row[$headerByKey['id']]);
                                if (!$Customer) {
                                    $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['id']]);
                                    $this->addErrors($message);

                                    return $this->renderWithError($form, $headers);
                                }
                                // 編集用にデフォルトパスワードをセット
                                $previous_password = $Customer->getPassword();
                            } else {
                                $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['id']]);
                                $this->addErrors($message);

                                return $this->renderWithError($form, $headers);
                            }
                        }

                        if (StringUtil::isBlank($row[$headerByKey['name01']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['name01']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setName01(StringUtil::trimAll($row[$headerByKey['name01']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['name02']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['name02']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setName02(StringUtil::trimAll($row[$headerByKey['name02']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['kana01']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['kana01']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setKana01(StringUtil::trimAll($row[$headerByKey['kana01']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['kana02']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['kana02']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setKana02(StringUtil::trimAll($row[$headerByKey['kana02']]));
                        }

                        if (StringUtil::isNotBlank($row[$headerByKey['company_name']])) {
                            $Customer->setCompanyName($row[$headerByKey['company_name']]);
                        }

                        if (StringUtil::isBlank($row[$headerByKey['postal_code']]) || !is_numeric($row[$headerByKey['postal_code']]) || strlen($row[$headerByKey['postal_code']]) != 7) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['postal_code']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setPostalCode(StringUtil::trimAll($row[$headerByKey['postal_code']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['pref_id']]) || !is_numeric($row[$headerByKey['pref_id']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['pref_id']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $pref = $this->prefRepository->find($row[$headerByKey['pref_id']]);

                            if ($pref->getName() == $row[$headerByKey['pref_name']]) {
                                $Customer->setPref($pref);
                            } else {
                                $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['pref_id']]);
                                $this->addErrors($message);

                                return $this->renderWithError($form, $headers);
                            }
                        }

                        if (StringUtil::isBlank($row[$headerByKey['addr01']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['addr01']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setAddr01(StringUtil::trimAll($row[$headerByKey['addr01']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['addr02']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['addr02']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setAddr02(StringUtil::trimAll($row[$headerByKey['addr02']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['email']]) || !filter_var($row[$headerByKey['email']], FILTER_VALIDATE_EMAIL)) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['email']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setEmail(StringUtil::trimAll($row[$headerByKey['email']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['phone_number']]) || !is_numeric($row[$headerByKey['phone_number']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['phone_number']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Customer->setPhoneNumber(StringUtil::trimAll($row[$headerByKey['phone_number']]));
                        }

                        if(StringUtil::isNotBlank($row[$headerByKey['sex']]) || is_numeric($row[$headerByKey['sex']])){
                            $sex = $this->sexRepository->find($row[$headerByKey['sex']]);
                            if ($sex->getname() == $row[$headerByKey['sex_name']]) {
                                $Customer->setSex($sex);
                            } else {
                                $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['sex']]);
                                $this->addErrors($message);

                                return $this->renderWithError($form, $headers);
                            }
                        }

                        if(StringUtil::isNotBlank($row[$headerByKey['job']]) || is_numeric($row[$headerByKey['job']])){
                            $job = $this->jobRepository->find($row[$headerByKey['job']]);
                            if ($job->getname() == $row[$headerByKey['job_name']]) {
                                $Customer->setJob($job);
                            } else {
                                $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['job']]);
                                $this->addErrors($message);

                                return $this->renderWithError($form, $headers);
                            }
                        }

                        if(StringUtil::isNotBlank($row[$headerByKey['birth']])){
                            $Customer->setBirth(new \DateTime($row[$headerByKey['birth']]));
                        }

                        if(StringUtil::isNotBlank($row[$headerByKey['first_buy_date']])){
                            $Customer->setFirstBuyDate(new \DateTime($row[$headerByKey['first_buy_date']]));
                        }

                        if(StringUtil::isNotBlank($row[$headerByKey['last_buy_date']])){
                            $Customer->setLastBuyDate(new \DateTime($row[$headerByKey['last_buy_date']]));
                        }

                        if(StringUtil::isNotBlank($row[$headerByKey['buy_times']])){
                            $Customer->setBuyTimes($row[$headerByKey['buy_times']]);
                        }

                        if(StringUtil::isNotBlank($row[$headerByKey['note']])){
                            $Customer->setNote($row[$headerByKey['note']]);
                        }

                        if(StringUtil::isNotBlank($row[$headerByKey['status']]) || is_numeric($row[$headerByKey['status']])){
                            $status = $this->customerStatusRepository->find($row[$headerByKey['status']]);
                            if ($status->getname() == $row[$headerByKey['status_name']]) {
                                $Customer->setStatus($status);
                            } else {
                                $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['status']]);
                                $this->addErrors($message);

                                return $this->renderWithError($form, $headers);
                            }
                        }

                        $time = new \DateTime();
                        if(StringUtil::isNotBlank($row[$headerByKey['create_date']])){
                            $Customer->setCreateDate(new \DateTime($row[$headerByKey['create_date']]));
                        }else{
                            $Customer->setCreateDate($time);
                        }

                        if(StringUtil::isNotBlank($row[$headerByKey['update_date']])){
                            $Customer->setUpdateDate(new \DateTime($row[$headerByKey['update_date']]));
                        }

                        $encoder = $this->encoderFactory->getEncoder($Customer);
                        if (empty($previous_password)) {
                            if ($Customer->getSalt() === null) {
                                $Customer->setSalt($encoder->createSalt());
                                $Customer->setSecretKey($this->customerRepository->getUniqueSecretKey());
                            }
                            $Customer->setPassword($encoder->encodePassword($this->eccubeConfig['eccube_default_password'], $Customer->getSalt()));
                        }

                    }
                    $this->entityManager->flush();
                    $this->entityManager->getConnection()->commit();

                    log_info('お客様のCSV登録が完了しました');
                    $message = 'admin.common.csv_upload_complete';
                    $this->session->getFlashBag()->add('eccube.admin.success', $message);
                    $cacheUtil->clearDoctrineCache();
                }
            }
        }

        return $this->renderWithError($form, $headers);
    }

    /**
     * アップロード用CSV雛形ファイルダウンロード
     *
     * @Route("/%eccube_admin_route%/customer/csv_template/{type}", requirements={"type" = "\w+"}, name="admin_customer_csv_template")
     *
     * @param $type
     *
     * @return StreamedResponse
     */
    public function csvTemplate(Request $request, $type)
    {
        if ($type == 'customer') {
            $headers = $this->getCustomerCsvHeader();
            $filename = 'customer.csv';
        } else {
            throw new NotFoundHttpException();
        }

        return $this->sendTemplateResponse($request, array_keys($headers), $filename);
    }

    /**
     * 登録、更新時のエラー画面表示
     *
     * @param FormInterface $form
     * @param array $headers
     * @param bool $rollback
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    protected function renderWithError($form, $headers, $rollback = true)
    {
        if ($this->hasErrors()) {
            if ($rollback) {
                $this->entityManager->getConnection()->rollback();
            }
        }

        return [
            'form' => $form->createView(),
            'headers' => $headers,
            'errors' => $this->errors,
        ];
    }

    /**
     * 登録、更新時のエラー画面表示
     */
    protected function addErrors($message)
    {
        $this->errors[] = $message;
    }

    /**
     * @return array
     */
    protected function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return boolean
     */
    protected function hasErrors()
    {
        return count($this->getErrors()) > 0;
    }

    /**
     * 商品登録CSVヘッダー定義
     *
     * @return array
     */
    protected function getCustomerCsvHeader()
    {
        return [
            trans('admin.customer.customer_csv.id_col') => [
                'id' => 'id',
                'description' => 'admin.customer.customer_csv.id_description',
                'required' => false,
            ],
            trans('admin.customer.customer_csv.name01_col') => [
                'id' => 'name01',
                'description' => 'admin.customer.customer_csv.name01_description',
                'required' => true,
            ],
            trans('admin.customer.customer_csv.name02_col') => [
                'id' => 'name02',
                'description' => 'admin.customer.customer_csv.name02_description',
                'required' => true,
            ],

            trans('admin.customer.customer_csv.kana01_col') => [
                'id' => 'kana01',
                'description' => 'admin.customer.customer_csv.kana01_description',
                'required' => true,
            ],
            trans('admin.customer.customer_csv.kana02_col') => [
                'id' => 'kana02',
                'description' => 'admin.customer.customer_csv.kana02_description',
                'required' => true,
            ],

            trans('admin.customer.customer_csv.company_name_col') => [
                'id' => 'company_name',
                'description' => 'admin.customer.customer_csv.company_name_description',
                'required' => false,
            ],

            trans('admin.customer.customer_csv.postal_code_col') => [
                'id' => 'postal_code',
                'description' => 'admin.customer.customer_csv.postal_code_description',
                'required' => true,
            ],

            trans('admin.customer.customer_csv.pref_id_col') => [
                'id' => 'pref_id',
                'description' => 'admin.customer.customer_csv.pref_id_description',
                'required' => true,
            ],
            trans('admin.customer.customer_csv.pref_name_col') => [
                'id' => 'pref_name',
                'description' => 'admin.customer.customer_csv.pref_name_description',
                'required' => true,
            ],
            trans('admin.customer.customer_csv.addr01_col') => [
                'id' => 'addr01',
                'description' => 'admin.customer.customer_csv.addr01_description',
                'required' => true,
            ],
            trans('admin.customer.customer_csv.addr02_col') => [
                'id' => 'addr02',
                'description' => 'admin.customer.customer_csv.addr02_description',
                'required' => true,
            ],

            trans('admin.customer.customer_csv.email_col') => [
                'id' => 'email',
                'description' => 'admin.customer.customer_csv.email_description',
                'required' => true,
            ],

            trans('admin.customer.customer_csv.phone_number_col') => [
                'id' => 'phone_number',
                'description' => 'admin.customer.customer_csv.phone_number_description',
                'required' => true,
            ],

            trans('admin.customer.customer_csv.sex_col') => [
                'id' => 'sex',
                'description' => 'admin.customer.customer_csv.sex_description',
                'required' => false,
            ],
            trans('admin.customer.customer_csv.sex_name_col') => [
                'id' => 'sex_name',
                'description' => 'admin.customer.customer_csv.sex_name_description',
                'required' => false,
            ],

            trans('admin.customer.customer_csv.job_col') => [
                'id' => 'job',
                'description' => 'admin.customer.customer_csv.job_description',
                'required' => false,
            ],
            trans('admin.customer.customer_csv.job_name_col') => [
                'id' => 'job_name',
                'description' => 'admin.customer.customer_csv.job_name_description',
                'required' => false,
            ],

            trans('admin.customer.customer_csv.birth_col') => [
                'id' => 'birth',
                'description' => 'admin.customer.customer_csv.birth_description',
                'required' => false,
            ],

            trans('admin.customer.customer_csv.first_buy_date_col') => [
                'id' => 'first_buy_date',
                'description' => 'admin.customer.customer_csv.first_buy_date_description',
                'required' => false,
            ],

            trans('admin.customer.customer_csv.last_buy_date_col') => [
                'id' => 'last_buy_date',
                'description' => 'admin.customer.customer_csv.last_buy_date_description',
                'required' => false,
            ],

            trans('admin.customer.customer_csv.buy_times_col') => [
                'id' => 'buy_times',
                'description' => 'admin.customer.customer_csv.buy_times_description',
                'required' => false,
            ],

            trans('admin.customer.customer_csv.note_col') => [
                'id' => 'note',
                'description' => 'admin.customer.customer_csv.note_description',
                'required' => false,
            ],

            trans('admin.customer.customer_csv.status_col') => [
                'id' => 'status',
                'description' => 'admin.customer.customer_csv.status_description',
                'required' => false,
            ],

            trans('admin.customer.customer_csv.status_name_col') => [
                'id' => 'status_name',
                'description' => 'admin.customer.customer_csv.status_name_description',
                'required' => false,
            ],

            trans('admin.customer.customer_csv.create_date_col') => [
                'id' => 'create_date',
                'description' => 'admin.customer.customer_csv.create_date_description',
                'required' => false,
            ],

            trans('admin.customer.customer_csv.update_date_col') => [
                'id' => 'update_date',
                'description' => 'admin.customer.customer_csv.update_date_description',
                'required' => false,
            ],
        ];
    }
}
