<!--columnn-->
@foreach($products as $key => $product)

<div class="col-lg-4">
    <!--Card-->
    <div class="card  wow fadeIn" data-wow-delay="0.2s">
     
        <!--Card image-->
        <div class="view overlay hm-white-slight">
            <img src="http://mdbootstrap.com/img/Photos/Horizontal/E-commerce/Products/img%20(32).jpg" class="img-fluid" alt="">
            <a href="#">
                <div class="mask"></div>
            </a>
        </div>
        <!--/.Card image-->

        <!--Card content-->
        <div class="card-block">
            <!--Title-->

            <h4 class="card-title">{{ $product->title }}</h4>
            <!--Text-->
            <p class="card-text">Lorem ipsum dolor sit amet, consectetur adipisicing elit. </p>
            <a href="#" class="btn btn-default">Buy now for <strong>{{ $product->mrp }}$</strong></a>
        </div>
        <!--/.Card content-->

    </div>
    <!--/.Card-->
</div>
@endforeach
<div class="container text-center">
  {{-- {{ $products->links() }} --}}
</div>

<!--end column -->