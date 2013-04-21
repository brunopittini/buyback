<?php
ini_set('display_errors','Off');

if (isset($_GET['isbn'])) {
    $ISBN = removeDashes(trim($_GET['isbn']));
    if (isValidIsbnLength($ISBN)) {
        $google = GetGoogle($ISBN);
        
        $data = array();
        $data['Isbn'] = $ISBN;
        $data['Title'] = $google['Title'];
        $data['Providers'] = array();
        $data['Providers'][0] = GetNeebo($ISBN);
        $data['Providers'][1] = GetFollett($ISBN);
        $data['Providers'][2] = GetAmazon($ISBN);
        
        if (!$data['Title']) $data['Title'] = $data['Providers'][0]['Title'];
        if (!$data['Title']) $data['Title'] = $data['Providers'][1]['Title'];
        if (!$data['Title']) $data['Title'] = '(Not Found)';
        
        // provider name replacement for UNL
        $data['Providers'][0]['Provider'] = 'Nebraska Bookstore';
        $data['Providers'][1]['Provider'] = 'University Bookstore';
        $data['Providers'][2]['Provider'] = 'Amazon.com Buyback';
        
        echo json_encode($data);
    } else {
        echo 'Invalid ISBN.';
    }
} else {
    echo 'No ISBN set.';
}

function isValidIsbnLength($ISBN) {
    if (is_null($ISBN)) {
        return false;
    }
    
    $ISBN = trim($ISBN);
    if (!(strlen($ISBN) == 13 || strlen($ISBN) == 10)) {
        return false;
    }
    return true;
}

function removeDashes($str) {
    return str_replace('-', '', $str);
}

function createEmptyResponse($provider, $price) {
    $data = array(
        'Provider' => $provider,
        'Title' => '',
        'Author' => '',
        'Edition' => '',
        'Isbn' => '',
        'Price' => $price
    );
    return $data;
}

function GetNeebo($ISBN) {
    $request = new HttpRequest('http://www.neebo.com/SellBack/LookupIsbns', HttpRequest::METH_POST);
    $request->setHeaders(array(
        'Host' => 'www.neebo.com',
        'Origin' => 'http://www.neebo.com',
        'X-Requested-With' => 'XMLHttpRequest'
    ));
    $request->setPostFields(array(
        'isbns' => $ISBN,
        'slug' => '135' // 135 is NE store id
    ));
    
    try {
        $response = json_decode($request->send()->getBody(), true);
        if (is_null($response)){
            return null;
        }
        
        if (count($response) > 0) {
            $data = array(
                'Provider' => 'Neebo',
                'Title' => $response[0]['Name'],
                'Author' => $response[0]['Author'],
                'Edition' => $response[0]['Edition'],
                'Isbn' => $response[0]['Isbn'],
                'Price' => trim($response[0]['PriceQuote']['PriceQuote'])
            );
        } else {
            $data = createEmptyResponse('Neebo', '0');
        }
    } catch (HttpException $ex) {
        //echo $ex;
        $data = createEmptyResponse('Neebo', 'error');
    }
    
    return $data;
}

function GetFollett($ISBN) {
    $req = new HttpRequest('http://www.bkstr.com/webapp/wcs/stores/servlet/BuybackMaterialsView?langId=-1&catalogId=10001&storeId=10051&schoolStoreId=10287', HttpRequest::METH_GET);
    $req->setHeaders(array(
        'Host' => 'www.bkstr.com'
    ));
    $cookies = $req->send()->getHeader('Set-Cookie');
    list($session, ) = explode(';', $cookies[0]);
    
    $request = new HttpRequest('http://www.bkstr.com/webapp/wcs/stores/servlet/BuybackSearch', HttpRequest::METH_POST);
    $request->setHeaders(array(
        'Host' => 'www.bkstr.com',
        'Origin' => 'http://www.bkstr.com',
        'Referer' => 'http://www.bkstr.com/webapp/wcs/stores/servlet/BuybackMaterialsView?langId=-1&catalogId=10001&storeId=10051&schoolStoreId=10287',
        'Cookie' => $session
    ));
    $request->setPostFields(array(
        'catalogId' => '10001',
        'schoolStoreId' => '10287', // 10287 is NE store id
        'isbn' => $ISBN
    ));
    
    try {
        $response = $request->send()->getBody();
        if (is_null($response)){
            return null;
        }
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML(str_replace('&nbsp;', ' ', $response));
        $content = $doc->getElementById('content');
        $form = $content->getElementsByTagName('form')->item(0);
        $tr = $form->getElementsByTagName('tr')->item(3);
        $td = $tr->getElementsByTagName('td')->item(1);
        if (!is_null($td)) {
            $info = $td->getElementsByTagName('strong')->item(0);
            $node = $info->firstChild;
            list( ,$title) = explode(':  ', trim($node->wholeText), 2);
            $node = $node->nextSibling->nextSibling;
            list( ,$edition) = explode(':  ', trim($node->wholeText), 2);
            $node = $node->nextSibling->nextSibling;
            list( ,$author) = explode(':  ', trim($node->wholeText), 2);
            $node = $node->nextSibling->nextSibling;
            //list( ,$publisher) = explode(':  ', trim($node->wholeText), 2);
            $node = $node->nextSibling->nextSibling;
            list( ,$isbn) = explode(':  ', trim($node->wholeText), 2);
            $node = $node->nextSibling->nextSibling->nextSibling;
            list( ,$price) = explode(':  ', trim($node->wholeText), 2);
            
            $data = array(
                'Provider' => 'Follett',
                'Title' => $title,
                'Author' => $author,
                'Edition' => $edition,
                'Isbn' => $isbn,
                'Price' => str_replace('$', '', trim($price))
            );
        } else {
            $data = createEmptyResponse('Follett', '0');
        }
    } catch (HttpException $ex) {
        //echo $ex;
        $data = createEmptyResponse('Follett', 'error');
    }
    
    return $data;
}

function GetAmazon($ISBN) {
    $request = new HttpRequest("http://www.amazon.com/gp/search/s/?i=textbooks-tradein&field-keywords=$ISBN", HttpRequest::METH_GET);
    $request->setHeaders(array(
        'Host' => 'www.amazon.com'
    ));
    
    try {
        $response = $request->send()->getBody();
        if (is_null($response)){
            return null;
        }
        
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML(str_replace('&nbsp;', ' ', $response));
        //$result = $doc->getElementById('result_0');
        $finder = new DomXPath($doc);
        $nodeId = 'result_0';
        $nodes = $finder->query("//*[contains(concat(' ', normalize-space(@id), ' '), ' $nodeId ')]");
        $node = $nodes->item(0);
        if (!is_null($node)) {
            $title_h3 = $node->getElementsByTagName('h3')->item(0);
            $title_span = $title_h3->getElementsByTagName('span')->item(0);
            $title = $title_span->firstChild->wholeText;
            
            $price = $node->getElementsByTagName('ul')->item(0)->firstChild->firstChild->firstChild->firstChild->wholeText;
        
            $data = array(
                'Provider' => 'Amazon',
                'Title' => $title,
            //    'Author' => $author,
            //    'Edition' => $edition,
            //    'Isbn' => $isbn,
                'Price' => str_replace('$', '', trim($price))
            );
        } else {
            $data = createEmptyResponse('Amazon', '0');
        }
    } catch (HttpException $ex) {
        //echo $ex;
        $data = createEmptyResponse('Amazon', 'error');
    }
    
    return $data;
}

function GetGoogle($ISBN) {
    $request = new HttpRequest("https://www.googleapis.com/books/v1/volumes?q=isbn:$ISBN", HttpRequest::METH_GET);
    $request->setHeaders(array(
        'Host' => 'www.googleapis.com'
    ));
    
    try {
        $response = json_decode($request->send()->getBody());
        if (is_null($response)){
            return null;
        }
        
        if ($response->totalItems == 1) {
            $item = $response->items[0];
            $info = $item->volumeInfo;
            
            if (!is_null($info)) {
                $data = array(
                    'Title' => $info->title,
                    'Subtitle' => $info->subtitle,
                    'Author' => $info->authors[0]
                );
            } else {
                $data = array(
                    'Title' => "",
                    'Subtitle' => "",
                    'Author' => ""
                );
            }
        }
    } catch (Exception $ex) {
        //echo $ex;
        $data = array(
            'Title' => "",
            'Subtitle' => "",
            'Author' => ""
        );
    }
    
    return $data;
}
?>