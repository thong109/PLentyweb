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

namespace Customize\Controller\Admin\Shop;

use Customize\Entity\Store;
use Customize\Repository\StoreRepository;
use Eccube\Controller\Admin\AbstractCsvImportController;
use Eccube\Entity\ProductImage;
use Eccube\Form\Type\Admin\CsvImportType;
use Eccube\Repository\Master\PrefRepository;
use Eccube\Service\CsvImportService;
use Eccube\Util\CacheUtil;
use Eccube\Util\StringUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvImportController extends AbstractCsvImportController
{
    private $errors = [];
    private $prefRepository;

    protected $storeRepository;

    public function __construct(
        PrefRepository $prefRepository,
        StoreRepository $storeRepository
    )
    {
        $this->storeRepository = $storeRepository;
        $this->prefRepository = $prefRepository;
    }

    /**
     * 商品登録CSVアップロード
     *
     * @Route("/%eccube_admin_route%/shop/shop_csv_upload", name="admin_shop_csv_import")
     * @Template("@admin/Shop/csv_shop.twig")
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function csvShop(Request $request, CacheUtil $cacheUtil)
    {
        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
        $headers = $this->getShopCsvHeader();
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
                            $Store = new Store();
                            $this->entityManager->persist($Store);
                        } else {
                            if (preg_match('/^\d+$/', $row[$headerByKey['id']])) {
                                $Store = $this->storeRepository->find($row[$headerByKey['id']]);
                                if (!$Store) {
                                    $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['id']]);
                                    $this->addErrors($message);

                                    return $this->renderWithError($form, $headers);
                                }
                            } else {
                                $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['id']]);
                                $this->addErrors($message);

                                return $this->renderWithError($form, $headers);
                            }
                        }

                        if (StringUtil::isBlank($row[$headerByKey['company_name']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['company_name']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Store->setCompanyName(StringUtil::trimAll($row[$headerByKey['company_name']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['company_name_kana']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['company_name_kana']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Store->setCompanyNameKana(StringUtil::trimAll($row[$headerByKey['company_name_kana']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['name']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['kana01']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Store->setName(StringUtil::trimAll($row[$headerByKey['name']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['name_kana']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['name_kana']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Store->setNameKana(StringUtil::trimAll($row[$headerByKey['name_kana']]));
                        }

                        if (StringUtil::isNotBlank($row[$headerByKey['name_sign']])) {
                            $Store->setNameSign($row[$headerByKey['name_sign']]);
                        }

                        if (StringUtil::isBlank($row[$headerByKey['postal_code']]) || !is_numeric($row[$headerByKey['postal_code']]) || strlen($row[$headerByKey['postal_code']]) != 7) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['postal_code']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Store->setPostalCode(StringUtil::trimAll($row[$headerByKey['postal_code']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['pref_id']]) || !is_numeric($row[$headerByKey['pref_id']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['pref_id']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $pref = $this->prefRepository->find($row[$headerByKey['pref_id']]);
                            if ($pref->getname() == $row[$headerByKey['pref_name']]) {
                                $Store->setPref($pref);
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
                            $Store->setAddr01(StringUtil::trimAll($row[$headerByKey['addr01']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['addr02']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['addr02']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Store->setAddr02(StringUtil::trimAll($row[$headerByKey['addr02']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['mail_send']]) || !filter_var($row[$headerByKey['mail_send']], FILTER_VALIDATE_EMAIL)) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['mail_send']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Store->setMailSend(StringUtil::trimAll($row[$headerByKey['mail_send']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['mail_contact']]) || !filter_var($row[$headerByKey['mail_contact']], FILTER_VALIDATE_EMAIL)) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['mail_contact']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Store->setMailContact(StringUtil::trimAll($row[$headerByKey['mail_contact']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['mail_feedback']]) || !filter_var($row[$headerByKey['mail_feedback']], FILTER_VALIDATE_EMAIL)) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['mail_feedback']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Store->setMailFeedBack(StringUtil::trimAll($row[$headerByKey['mail_feedback']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['mail_receive_error']]) || !filter_var($row[$headerByKey['mail_receive_error']], FILTER_VALIDATE_EMAIL)) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['mail_receive_error']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Store->setMailReceiveError(StringUtil::trimAll($row[$headerByKey['mail_receive_error']]));
                        }

                        if (StringUtil::isBlank($row[$headerByKey['phone_number']]) || !is_numeric($row[$headerByKey['phone_number']])) {
                            $message = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $headerByKey['phone_number']]);
                            $this->addErrors($message);

                            return $this->renderWithError($form, $headers);
                        } else {
                            $Store->setPhoneNumber(StringUtil::trimAll($row[$headerByKey['phone_number']]));
                        }

                        if (StringUtil::isNotBlank($row[$headerByKey['description']])) {
                            $Store->setDescription($row[$headerByKey['description']]);
                        }

                        $this->createShopImage($row, $Store, $data, $headerByKey);

                        $time = new \DateTime();
                        if (StringUtil::isNotBlank($row[$headerByKey['create_date']])) {
                            $Store->setCreateDate(new \DateTime($row[$headerByKey['create_date']]));
                        } else {
                            $Store->setCreateDate($time);
                        }

                        if (StringUtil::isNotBlank($row[$headerByKey['update_date']])) {
                            $Store->setUpdateDate(new \DateTime($row[$headerByKey['update_date']]));
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
     *
     * @param $row
     * @param CsvImportService $data
     * @param $headerByKey
     */
    protected function createShopImage($row, Store $Store, $data, $headerByKey) {
        if (!isset($row[$headerByKey['image']])) {
            return;
        }

        if (StringUtil::isNotBlank($row[$headerByKey['image']])) {

            $imageStore = explode(',', $row[$headerByKey['image']]);
            $pattern = "/\\$|^.*.\.\\\.*|\/$|^.*.\.\/\.*/";
            $fileName = StringUtil::trimAll($imageStore[0]);

            if (strlen($fileName) > 0 && preg_match($pattern, $fileName)) {
                $message = trans('admin.common.csv_invalid_image', ['%line%' => $data->key() + 1, '%name%' => $headerByKey['image']]);
                $this->addErrors($message);
            } else {
                // 空文字は登録対象外
                if (!empty($fileName)) {
                    $Store->setImage($fileName);
                }
            }
        }
    }
    /**
     * アップロード用CSV雛形ファイルダウンロード
     *
     * @Route("/%eccube_admin_route%/shop/csv_template/{type}", requirements={"type" = "\w+"}, name="admin_shop_csv_template")
     *
     * @param $type
     *
     * @return StreamedResponse
     */
    public function csvTemplate(Request $request, $type)
    {
        if ($type == 'shop') {
            $headers = $this->getShopCsvHeader();
            $filename = 'shop.csv';
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
    protected function getShopCsvHeader()
    {
        return [
            trans('admin.shop.table.store_ID') => [
                'id' => 'id',
                'description' => 'admin.customer.customer_csv.id_description',
                'required' => false,
            ],

            trans('admin.shop.company_name') => [
                'id' => 'company_name',
                'description' => 'admin.shop.company_name_description',
                'required' => true,
            ],

            trans('admin.shop.company_name_kana') => [
                'id' => 'company_name_kana',
                'description' => 'admin.shop.company_name_kana_description',
                'required' => true,
            ],

            trans('admin.shop.name') => [
                'id' => 'name',
                'description' => 'admin.shop.name_description',
                'required' => true,
            ],

            trans('admin.shop.name_kana') => [
                'id' => 'name_kana',
                'description' => 'admin.shop.name_kana_description',
                'required' => true,
            ],

            trans('admin.shop.name_sign') => [
                'id' => 'name_sign',
                'description' => 'admin.shop.name_sign_description',
                'required' => true,
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

            trans('admin.shop.phone_number') => [
                'id' => 'phone_number',
                'description' =>  'admin.customer.customer_csv.phone_number_description',
                'required' => false,
            ],

            trans('admin.shop.image') => [
                'id' => 'image',
                'description' => 'admin.shop.image_description',
                'required' => false,
            ],

            trans('admin.shop.mail_send') => [
                'id' => 'mail_send',
                'description' => 'admin.shop.mail_send_description',
                'required' => true,
            ],

            trans('admin.shop.mail_contact') => [
                'id' => 'mail_contact',
                'description' => 'admin.shop.mail_contact_description',
                'required' => false,
            ],

            trans('admin.shop.mail_feedback') => [
                'id' => 'mail_feedback',
                'description' => 'admin.shop.mail_feedback_description',
                'required' => true,
            ],

            trans('admin.shop.mail_receive_error') => [
                'id' => 'mail_receive_error',
                'description' => 'admin.shop.mail_receive_error_description',
                'required' => true,
            ],

            trans('admin.shop.description_detail') => [
                'id' => 'description',
                'description' => 'admin.shop.description_detail_description',
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
