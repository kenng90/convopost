<section id="features" class="w-full bg-white " >
    <div class="pB-10 mx-auto max-w-7xl md:px-8">

        
        @for ($i = 0; $i < count($features); $i++) 
            <?php
                $feature = $features[$i];
            ?>
            @if ($i ==0 || $i> 2 || (($i+1)>count($features)-1))
                @include('wpsupportlanding::landing.partials.fullproduct')
            @else
                <?php
                    $feature2 = $features[$i+1];
                    $i++;   
                ?>
                @include('wpsupportlanding::landing.partials.twoproducts')
            
            @endif
        @endfor


       

       




    </div>
</section>