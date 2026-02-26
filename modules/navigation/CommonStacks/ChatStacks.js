import React from "react";
import { createStackNavigator } from "@react-navigation/stack";
import { Language } from '../../constants'

// screens
import Chats from "./../../screens/Chats";
import chatDetails from './../../screens/ChatDetails'


// header for screens
import Header from "../../components/Header";


const Stack = createStackNavigator();

export default function  ChatStack(props) {
    return (
        <Stack.Navigator presentation="card" >
            <Stack.Screen
                name="Chats"
                component={Chats}
                options={{
                    headerMode:"screen",
                    header: ({ navigation, scene }) => (
                        <Header title={Language.chats} routeName={"Chats"}  navigation={navigation} scene={scene} />
                    ),
                    headerTransparent: false,
                    cardStyle: { backgroundColor: "#F8F9FE" }
                }}
            />
            <Stack.Screen
                name="ChatDetails"
                component={chatDetails}
                options={({ route, navigation }) => ({
                header: ({ scene }) => (
                    <Header
                    back
                    title={route.params.item.name || Language.chat_details}
                    routeName={"chatDetails"}
                    navigation={navigation}
                    scene={scene}
                    />
                ),
                headerTransparent: false,
                cardStyle: { backgroundColor: "#F8F9FE" }
                })}
            />
        </Stack.Navigator>
    )
}