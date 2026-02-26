import React from 'react';
import { useNavigation } from '@react-navigation/native';
import PropTypes from 'prop-types';
import { StyleSheet, Dimensions, Image, TouchableWithoutFeedback } from 'react-native';
import { Block, Text, theme, Button } from 'galio-framework';
import Modal, { ModalContent } from 'react-native-modals';
import { argonTheme } from '../constants';
import Dialog, { DialogContent } from 'react-native-popup-dialog';
import config from './../config'
import Select from "./../components/Select"

const { width } = Dimensions.get('screen');

class Card extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      visible: false,
      quantity:1,
      item:this.props.item
    }
    this.increment = this.increment.bind(this);
    this.decrement = this.decrement.bind(this);
    this.addToCard = this.addToCard.bind(this);
  }

  increment(){
    this.setState({
      quantity:this.state.quantity+1
    })
  }

  decrement(){
    if(this.state.quantity > 1){
      this.setState({
        quantity:this.state.quantity-1
      })
    }else{
      //do nothing
    }
    
  }

  addToCard(){
    this.state.item = Object.assign({ totoalPrice: this.state.item.price*this.state.quantity,quantity:this.state.quantity }, this.state.item);
    console.log(this.state.item)
  }

  showCartOptions(){
    if(this.props.isCart){
      return (<Block row space={"evenly"} style={{marginTop:2, marginBottom:10}}>
                <TouchableWithoutFeedback onPress={()=>{this.props.callback(0,this.props.id)}}>
                <Block style={[styles.actionButtons]}>
                  <Block flex row middle>
                    <Text size={12}>{"DELETE"}</Text>  
                  </Block>
                </Block>
                </TouchableWithoutFeedback>

                
        </Block>)
    }else{
      return null;
    }
  }

  render() {
    const { item, horizontal, full, style, ctaColor, imageStyle, from } = this.props;

    const imageStyles = [
      full ? styles.fullImage : styles.horizontalImage,
      imageStyle
    ];
    const cardContainer = [styles.card, styles.shadow, style];
    const imgContainer = [styles.imageContainer,horizontal ? styles.horizontalStyles : styles.verticalStyles,styles.shadow];
    if (from != "items") {
      if (from == "cities") {
        return (
          <Block row={horizontal} card flex style={cardContainer}>
              <Block flex style={imgContainer}>
                <Image source={{ uri: item.logo }} style={imageStyles} />
              </Block>
              <Block flex style={styles.cardDescription}>
                <Text bold style={styles.cardTitle}>{item.name}</Text>
                
              </Block>
          </Block>
        );
      }else{
        return (
          <Block row={horizontal} card flex style={cardContainer}>
              <Block flex style={imgContainer}>
                <Image source={{ uri: item.logom.indexOf('http')!=-1?item.logom:(config.domain+"/"+item.logom) }} style={imageStyles} />
              </Block>
              <Block flex style={styles.cardDescription}>
                <Text bold style={styles.cardTitle}>{item.name}</Text>
                <Text muted>{item.description}</Text>
              </Block>
          </Block>
        );
      } 
      
    } else {


       {/* ITEM IN CARD  */}
      return (
        <Block>
          <Block row={horizontal} card flex style={cardContainer}>
              <Block flex style={imgContainer}>
                <Image source={{ uri: item.logom.indexOf('http')!=-1?item.logom:(config.domain+"/"+item.logom) }} style={imageStyles} />
              </Block>

              <Block flex space="between" style={styles.cardDescription}>
                <Text bold style={styles.cardTitle}>{this.props.isCart ? item.qty + " x " : ""}{item.name}</Text>
                <Text muted size={14} style={styles.cardTitle}>{item.short_description}</Text>

                {item.extrasSelected && item.extrasSelected.length>0 &&
                  <Text muted size={14} style={styles.cardTitle}>{Language.extras+": "+Object.keys(item.extrasSelected).map((key)=>{return item.extrasSelected[key].name+" " })}</Text>
                }
                {item.variant &&
                  <Text size={14} style={styles.cardTitle}>{Language.options+": "+Object.keys(JSON.parse(item.variant.options)).map((key)=>{return JSON.parse(item.variant.options)[key]+" " })}</Text>
                }
                
                <Text size={14} style={styles.cardTitle}>{config.currencySymbolInFront ? config.currencySign : ''}{typeof item.price === 'string' ? item.price : parseFloat(item.price).toFixed(2)}{!config.currencySymbolInFront ? ' ' + config.currencySign : ''}</Text>
              </Block>

              
          </Block>
          {this.showCartOptions()}
        </Block>
      )
    }
  }

}

Card.propTypes = {
  item: PropTypes.object,
  horizontal: PropTypes.bool,
  full: PropTypes.bool,
  ctaColor: PropTypes.string,
  imageStyle: PropTypes.any,
}

const styles = StyleSheet.create({
  actionButtons: {
    width: width/2,
    backgroundColor: '#DCDCDC',
    paddingHorizontal: 16,
    paddingTop: 10,
    paddingBottom: 9.5,
    borderRadius: 8,
    shadowColor: "rgba(0, 0, 0, 0.1)",
    shadowOffset: { width: 0, height: 4 },
    shadowRadius: 8,
    shadowOpacity: 0.2,
    elevation: 3,
  },
  card: {
    backgroundColor: theme.COLORS.WHITE,
    marginVertical: theme.SIZES.BASE,
    borderWidth: 0,
    minHeight: 114,
    marginBottom: 20,
    borderRadius: 12,
    padding: 8,
  },
  cardTitle: {
    flex: 1,
    flexWrap: 'wrap',
    paddingBottom: 8,
  },
  cardDescription: {
    padding: theme.SIZES.BASE,
  },
  imageContainer: {
    borderRadius: 8,
    elevation: 1,
    overflow: 'hidden',
    resizeMode: "cover",
    margin: 4,
  },
  image: {
    // borderRadius: 3,
  },
  horizontalImage: {
    height: 122,
    width: 'auto',
  },
  horizontalStyles: {
    borderRadius: 8,
  },
  verticalStyles: {
    borderRadius: 8,
  },
  fullImage: {
    height: 200
  },
  shadow: {
    shadowColor: theme.COLORS.BLACK,
    shadowOffset: { width: 0, height: 4 },
    shadowRadius: 8,
    shadowOpacity: 0.15,
    elevation: 3,
  },
});

export default Card;