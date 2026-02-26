import React from "react";
import { StyleSheet, TouchableWithoutFeedback, Image, TouchableOpacity } from "react-native";
import PropTypes from "prop-types";
import { Block, Text, theme  } from "galio-framework";
import Icon from "./Icon";
import { argonTheme } from "../constants";
import moment from "moment";
import { render } from "react-dom";
import {Button} from '.';




export default class MessageElement extends React.Component {

    showBigImage = (image) => {
       console.log("Show big image", image);
    }

    renderImage = (image) => {
        return (
            <TouchableOpacity style={{ width: 250 }} onPress={() => this.showBigImage(image)}>
                <Image source={{ uri: image }} style={{ aspectRatio: 1, width: "100%" }} />
            </TouchableOpacity>
        );
    };

    renderButtons = (buttonsJSON) => {
        //Convert the JSON string to object
        const buttons = JSON.parse(buttonsJSON);
        return buttons.map((button, index) => {
            return (
                <Block key={index} style={{ marginTop: 10, alignSelf: 'flex-end' }}>
                    <Button color={"secondary"}   >
                        <Text color="#000">{button.reply?button.reply.title:(button.parameters&&button.parameters?button.parameters.display_text:"")}</Text>
                    </Button>
                </Block>
            );
        });
    }

    renderMessageContent = (msg,isFromContact) => {
        return (
            <Block>
               {msg.header_image && (
                        this.renderImage(msg.header_image)
                    )}
                    {msg.header_text && (
                          <Text style={{ fontWeight: 'bold', fontSize: 18 }} color={isFromContact?argonTheme.COLORS.WHITE:argonTheme.COLORS.TEXT}>{msg.header_text}</Text>
                    )}
                    <Text style={{  fontSize: 16 }} color={isFromContact?argonTheme.COLORS.WHITE:argonTheme.COLORS.TEXT}>
                        {msg.value}
                    </Text>
                    
                    {msg.footer_text && (
                          <Text style={{ opacity:0.8, fontSize: 14, marginTop:5 }} color={isFromContact?argonTheme.COLORS.WHITE:argonTheme.COLORS.TEXT}>{msg.footer_text}</Text>
                    )}
                    
                    {msg.buttons && (
                        this.renderButtons(msg.buttons)
                    )}
            </Block>
        );
    }

    render() {
        const {
            msg,
            showDate,
            currentDate
          } = this.props;
          
       
        if (msg.is_message_by_contact) {
            return (
                <TouchableOpacity key={msg.id}>
                    {showDate && (
                        <Block center style={{ marginBottom: 10, marginTop: 10 }}>
                            <Text style={styles.time}>{currentDate}</Text>
                        </Block>
                    )}
                    <Block row space={null}>
                        <Block style={styles.messageCardWrapper}>
                            <Block style={[styles.messageCard, styles.shadow]}>
                              {(this.renderMessageContent(msg))}
                            </Block>
                            <Block right>
                                <Text style={styles.time}>{moment(msg.created_at).format("HH:mm")}</Text>
                            </Block>
                        </Block>
                    </Block>
                </TouchableOpacity>
            );
        }

        return (
            <Block key={msg.id} right>
                {showDate && (
                    <Block center style={{ marginBottom: 10, marginTop: 10 }}>
                        <Text style={styles.time}>{currentDate}</Text>
                    </Block>
                )}
                <Block row>
                    <Block style={styles.messageCardWrapper}>
                        <Block style={[styles.messageCardPersonal, styles.shadow]}>
                        {(this.renderMessageContent(msg,true))}
                        </Block>
                        <Block right>
                            <Text style={[styles.time, { marginRight: 8 }]}>{moment(msg.created_at).format("HH:mm")}</Text>
                        </Block>
                    </Block>
                </Block>
            </Block>
        );
    };

   
}

MessageElement.propTypes = {
    msg: PropTypes.object,
    showDate: PropTypes.bool,
    currentDate: PropTypes.string
};

const styles = StyleSheet.create({
    container: {},
    messageFormContainer: {
      height: 96,
      paddingHorizontal: 16,
      paddingTop: 16,
      paddingBottom: 32
    },
    input: {
      // width: width * 0.93,
      height: theme.SIZES.BASE * 3,
      backgroundColor: theme.COLORS.WHITE,
      shadowColor: theme.COLORS.BLACK,
      shadowOffset: { width: 0, height: 0.7 },
      shadowRadius: 3,
      shadowOpacity: 0.07,
      elevation: 2,
    },
    iconButton: {
      width: 40,
      height: 40,
      backgroundColor: "transparent"
    },
    messagesWrapper: {
      flexGrow: 1,
      top: 0,
      paddingLeft: 8,
      paddingRight: 8,
      paddingVertical: 16,
      paddingBottom: 56
    },
    messageCardWrapper: {
      maxWidth: "85%",
      marginLeft: 8,
      marginBottom: 32
    },
    messageCard: {
      paddingHorizontal: 8,
      paddingVertical: 16,
      borderRadius: 6,
      backgroundColor: theme.COLORS.WHITE
    },
    messageCardPersonal: {
      paddingHorizontal: 8,
      paddingVertical: 16,
      borderRadius: 6,
      marginRight: 8,
      backgroundColor: theme.COLORS.SUCCESS
    },
    shadow: {
      shadowColor: "rgba(0, 0, 0, 0.1)",
      shadowOffset: { width: 0, height: 1 },
      shadowRadius: 3,
      shadowOpacity: 1
    },
    time: {
     
      fontSize: 11,
      opacity: 0.5,
      marginTop: 8
    },
    avatar: {
      height: 40,
      width: 40,
      borderRadius: 20,
      marginBottom: theme.SIZES.BASE
    }
  });
